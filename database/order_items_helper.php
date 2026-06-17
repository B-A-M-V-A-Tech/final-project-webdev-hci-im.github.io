<?php

function orderItemsExtractName($entry) {
    if (is_string($entry)) {
        return trim($entry);
    }
    if (!is_array($entry)) {
        return '';
    }
    if (!empty($entry['item']['name'])) {
        return trim((string) $entry['item']['name']);
    }
    if (!empty($entry['name'])) {
        return trim((string) $entry['name']);
    }
    return '';
}

function orderItemsFormatLabel($entry) {
    $name = orderItemsExtractName($entry);
    if ($name === '') {
        return '';
    }

    $qty = 1;
    if (is_array($entry)) {
        $qty = max(1, intval($entry['qty'] ?? $entry['quantity'] ?? 1));
    }

    return $qty > 1 ? ($name . ' x' . $qty) : $name;
}

function orderItemsNormalizeList($items) {
    $labels = array();
    foreach ($items as $entry) {
        $label = orderItemsFormatLabel($entry);
        if ($label !== '') {
            $labels[] = $label;
        }
    }
    return implode(', ', $labels);
}

function orderItemsNormalizeForStorage($items) {
    if (is_string($items)) {
        $trimmed = trim($items);
        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return orderItemsNormalizeList($decoded);
        }

        return $trimmed;
    }

    if (!is_array($items)) {
        return '';
    }

    return orderItemsNormalizeList($items);
}

function orderItemsCount($itemsRaw) {
    $raw = trim((string) $itemsRaw);
    if ($raw === '') {
        return 0;
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $count = 0;
        foreach ($decoded as $entry) {
            if (is_array($entry)) {
                $count += max(1, intval($entry['qty'] ?? $entry['quantity'] ?? 1));
            } else {
                $count += 1;
            }
        }
        return max($count, count($decoded));
    }

    $parts = preg_split('/\s*,\s*|\s*\|\s*|\n+/', $raw);
    $parts = array_values(array_filter(array_map('trim', $parts), function ($part) {
        return $part !== '';
    }));

    return count($parts) ?: 1;
}
