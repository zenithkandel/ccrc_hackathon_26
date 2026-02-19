/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Storage Handler Implementation
 * ============================================================================
 * 
 * Implements a FIFO offline data queue on LittleFS using a JSONL file format
 * (one JSON record per line). This provides store-and-forward capability
 * for when WiFi connectivity is lost.
 * 
 * Queue Management Strategy:
 *   - Records are appended as newline-delimited JSON lines to /queue.jsonl
 *   - Queue count is tracked in RAM and synced from file on boot
 *   - When the queue exceeds MAX_QUEUE_SIZE (500), the oldest records
 *     are discarded by rewriting the file with only the newest entries
 *   - On flush, each record is sent via callback; failures are retained
 * 
 * LittleFS is chosen over SPIFFS because:
 *   - LittleFS is actively maintained (SPIFFS is deprecated on ESP32)
 *   - LittleFS has journaling for power-loss safety (important in vehicles)
 *   - LittleFS supports directories and has better wear leveling
 * 
 * Storage Considerations:
 *   - Each JSON record is approximately 200 bytes
 *   - 500 records ≈ 100KB, well within ESP32 LittleFS partition capacity
 *   - ESP32 default LittleFS partition is typically 1.5MB
 * ============================================================================
 */

#include "storage_handler.h"
#include "config.h"
#include <LittleFS.h>

// --- In-memory record count for fast access ---
static int _queueCount = 0;

// ---------------------------------------------------------------------------
// Internal helper: count lines in the queue file
// ---------------------------------------------------------------------------
static int _countLines() {
    if (!LittleFS.exists(QUEUE_FILE)) {
        return 0;
    }

    File f = LittleFS.open(QUEUE_FILE, "r");
    if (!f) return 0;

    int count = 0;
    while (f.available()) {
        String line = f.readStringUntil('\n');
        if (line.length() > 0) {
            count++;
        }
    }
    f.close();
    return count;
}

// ---------------------------------------------------------------------------
// Internal helper: trim queue to keep only the newest maxKeep records
// Discards the oldest records (FIFO eviction from the front of the file).
// ---------------------------------------------------------------------------
static void _trimQueue(int maxKeep) {
    if (!LittleFS.exists(QUEUE_FILE)) return;

    // Read all lines into memory
    File f = LittleFS.open(QUEUE_FILE, "r");
    if (!f) return;

    // Collect all lines
    std::vector<String> lines;
    lines.reserve(maxKeep + 10);
    while (f.available()) {
        String line = f.readStringUntil('\n');
        line.trim();
        if (line.length() > 0) {
            lines.push_back(line);
        }
    }
    f.close();

    // If within limits, no trimming needed
    if ((int)lines.size() <= maxKeep) {
        _queueCount = lines.size();
        return;
    }

    // Calculate how many to skip (oldest records to discard)
    int skip = lines.size() - maxKeep;
    Serial.print(F("[STORAGE] Trimming queue: discarding "));
    Serial.print(skip);
    Serial.println(F(" oldest records"));

    // Rewrite file with only the newest records
    f = LittleFS.open(QUEUE_FILE, "w");
    if (!f) {
        Serial.println(F("[STORAGE] ERROR: Failed to rewrite queue file"));
        return;
    }

    for (int i = skip; i < (int)lines.size(); i++) {
        f.println(lines[i]);
    }
    f.close();

    _queueCount = maxKeep;
}

// ============================================================================
// PUBLIC API
// ============================================================================

/**
 * Initialize LittleFS filesystem.
 * On first use (or after flash erase), the partition is formatted automatically.
 */
bool storageInit() {
    if (!LittleFS.begin(true)) {  // true = format on first mount failure
        Serial.println(F("[STORAGE] ERROR: LittleFS mount failed even after format"));
        return false;
    }

    // Sync in-memory count with actual file contents
    _queueCount = _countLines();

    Serial.print(F("[STORAGE] LittleFS mounted. Queue contains "));
    Serial.print(_queueCount);
    Serial.println(F(" records"));

    return true;
}

/**
 * Append a JSON record to the offline queue.
 * Enforces the MAX_QUEUE_SIZE limit by discarding oldest records if needed.
 */
bool storageEnqueue(const String& jsonLine) {
    // Enforce queue size limit before adding
    if (_queueCount >= MAX_QUEUE_SIZE) {
        // Keep (MAX_QUEUE_SIZE - 1) records to make room for the new one
        _trimQueue(MAX_QUEUE_SIZE - 1);
    }

    // Append the new record
    File f = LittleFS.open(QUEUE_FILE, "a");
    if (!f) {
        Serial.println(F("[STORAGE] ERROR: Failed to open queue file for append"));
        return false;
    }

    f.println(jsonLine);
    f.close();
    _queueCount++;

    Serial.print(F("[STORAGE] Enqueued record. Queue size: "));
    Serial.println(_queueCount);

    return true;
}

/**
 * Get current queue depth.
 */
int storageGetCount() {
    return _queueCount;
}

/**
 * Flush the offline queue by attempting to send each record.
 * 
 * Records are sent oldest-first (FIFO). Successfully sent records are
 * removed; failed records remain in the queue for the next flush attempt.
 * 
 * @param sendFunc  Lambda/function: bool(const String& json) — returns true on success
 * @return number of successfully sent records
 */
int storageFlush(std::function<bool(const String&)> sendFunc) {
    if (_queueCount == 0 || !LittleFS.exists(QUEUE_FILE)) {
        return 0;
    }

    Serial.print(F("[STORAGE] Flushing queue ("));
    Serial.print(_queueCount);
    Serial.println(F(" records)..."));

    // Read all records into memory
    File f = LittleFS.open(QUEUE_FILE, "r");
    if (!f) {
        Serial.println(F("[STORAGE] ERROR: Failed to open queue for flush"));
        return 0;
    }

    std::vector<String> lines;
    lines.reserve(_queueCount);
    while (f.available()) {
        String line = f.readStringUntil('\n');
        line.trim();
        if (line.length() > 0) {
            lines.push_back(line);
        }
    }
    f.close();

    // Attempt to send each record
    std::vector<String> failed;
    int sentCount = 0;

    for (const auto& line : lines) {
        if (sendFunc(line)) {
            sentCount++;
        } else {
            // Keep failed records for retry
            failed.push_back(line);
            // Stop trying after first failure to avoid blocking too long
            // Remaining unsent records are also kept
            break;
        }
    }

    // Add remaining unsent records (after the break) to failed list
    for (int i = sentCount + (int)failed.size(); i < (int)lines.size(); i++) {
        failed.push_back(lines[i]);
    }

    // Rewrite the queue file with only failed/remaining records
    if (failed.empty()) {
        // All sent successfully — remove the file
        LittleFS.remove(QUEUE_FILE);
        _queueCount = 0;
        Serial.println(F("[STORAGE] Queue fully flushed and cleared"));
    } else {
        // Write back only the failed records
        f = LittleFS.open(QUEUE_FILE, "w");
        if (f) {
            for (const auto& line : failed) {
                f.println(line);
            }
            f.close();
        }
        _queueCount = failed.size();
        Serial.print(F("[STORAGE] Flush partial: sent="));
        Serial.print(sentCount);
        Serial.print(F(", remaining="));
        Serial.println(_queueCount);
    }

    return sentCount;
}

/**
 * Clear all records from the offline queue.
 */
void storageClear() {
    if (LittleFS.exists(QUEUE_FILE)) {
        LittleFS.remove(QUEUE_FILE);
    }
    _queueCount = 0;
    Serial.println(F("[STORAGE] Queue cleared"));
}
