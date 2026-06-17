<?php

function cleanPinimgUrl($url) {
    $url = trim(html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8'));
    if (preg_match('#^(https://i\.pinimg\.com/(?:originals|\d+x)/[a-f0-9/]+\.(?:jpe?g|png|webp|gif))(?:\?[^\s"\'<>]*)?#i', $url, $match)) {
        return $match[1];
    }
    return $url;
}

function isUsablePinimgUrl($url) {
    return (bool) preg_match('#^https://i\.pinimg\.com/(?:originals|\d+x)/[a-f0-9/]+\.(?:jpe?g|png|webp|gif)(?:\?[^\s"\'<>]*)?$#i', $url);
}

function extractPinterestImageFromHtml($html) {
    if (!is_string($html) || $html === '') {
        return '';
    }

    $pinimgUrls = array();
    if (preg_match_all('#https://i\.pinimg\.com/(?:originals|\d+x)/[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]+\.(?:jpe?g|png|webp|gif)(?:\?[^\s"\'<>]*)?#i', $html, $matches)) {
        $pinimgUrls = array_values(array_unique($matches[0]));
        foreach ($pinimgUrls as $url) {
            if (stripos($url, '/originals/') !== false) {
                return cleanPinimgUrl($url);
            }
        }
        if (!empty($pinimgUrls)) {
            return cleanPinimgUrl($pinimgUrls[0]);
        }
    }

    $ogCandidates = array();
    if (preg_match('#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $match)) {
        $ogCandidates[] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
    }
    if (preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']#i', $html, $match)) {
        $ogCandidates[] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
    }
    foreach ($ogCandidates as $candidate) {
        $candidate = cleanPinimgUrl($candidate);
        if (isUsablePinimgUrl($candidate)) {
            return $candidate;
        }
    }

    if (preg_match('#"image"\s*:\s*"(https://i\.pinimg\.com/[^"]+)"#i', $html, $match)) {
        $candidate = cleanPinimgUrl(stripslashes($match[1]));
        if (isUsablePinimgUrl($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function resolveMenuImageUrl($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $url)) {
        throw new Exception('Image link must start with http:// or https://');
    }

    if (preg_match('#^https?://i\.pinimg\.com/#i', $url)) {
        return cleanPinimgUrl($url);
    }

    if (preg_match('#\.(jpe?g|png|webp|gif)(\?.*)?$#i', $url)) {
        return $url;
    }

    if (!preg_match('#pinterest\.com|pin\.it#i', $url)) {
        return $url;
    }

    if (!function_exists('curl_init')) {
        throw new Exception('Server cannot resolve Pinterest links. Paste the direct i.pinimg.com image address instead.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => array('Accept-Language: en-US,en;q=0.9'),
    ));
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400 || !is_string($html) || $html === '') {
        throw new Exception('Could not open this Pinterest link.');
    }

    $resolved = extractPinterestImageFromHtml($html);
    if ($resolved === '') {
        throw new Exception('Could not find an image on this Pinterest link. Copy the image address (i.pinimg.com) instead.');
    }

    return $resolved;
}
