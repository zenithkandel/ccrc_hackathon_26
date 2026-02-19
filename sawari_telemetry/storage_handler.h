/**
 * ============================================================================
 * SAWARI Bus Telemetry Device - Storage Handler Header
 * ============================================================================
 * Manages offline data queue on LittleFS for store-and-forward operation.
 * When WiFi is unavailable, telemetry records are queued locally. When WiFi
 * reconnects, queued data is flushed to the server automatically.
 * ============================================================================
 */

#ifndef STORAGE_HANDLER_H
#define STORAGE_HANDLER_H

#include <Arduino.h>
#include <functional>

/**
 * Initialize LittleFS filesystem.
 * Formats the partition on first use if mount fails.
 * @return true if filesystem mounted successfully
 */
bool storageInit();

/**
 * Add a JSON record to the offline queue.
 * If the queue exceeds MAX_QUEUE_SIZE, the oldest record is discarded.
 * 
 * @param jsonLine  A single-line JSON string (no newlines within)
 * @return true if the record was successfully written
 */
bool storageEnqueue(const String& jsonLine);

/**
 * Get the current number of records in the offline queue.
 * @return record count (0 if file doesn't exist)
 */
int storageGetCount();

/**
 * Flush the offline queue by sending each record via the provided callback.
 * 
 * Records that fail to send are kept in the queue for the next attempt.
 * Records that succeed are removed.
 * 
 * @param sendFunc  Callback that takes a JSON string and returns true on success
 * @return number of records successfully sent
 */
int storageFlush(std::function<bool(const String&)> sendFunc);

/**
 * Clear all records from the offline queue.
 */
void storageClear();

#endif // STORAGE_HANDLER_H
