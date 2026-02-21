<?php
/*
 * PHP QR Code encoder
 *
 * Main library file
 *
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
 *
 * PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010 Dominik Dzienia <deltalab at poczta dot fm>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

define('QR_MODE_NUL', -1);
define('QR_MODE_NUM', 0);
define('QR_MODE_AN', 1);
define('QR_MODE_8', 2);
define('QR_MODE_KANJI', 3);
define('QR_MODE_STRUCTURE', 4);

define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

define('QR_MASK_PATTERN_AUTO', -1);
define('QR_MASK_PATTERN_0', 0);
define('QR_MASK_PATTERN_1', 1);
define('QR_MASK_PATTERN_2', 2);
define('QR_MASK_PATTERN_3', 3);
define('QR_MASK_PATTERN_4', 4);
define('QR_MASK_PATTERN_5', 5);
define('QR_MASK_PATTERN_6', 6);
define('QR_MASK_PATTERN_7', 7);

define('QR_VERSION_MAX', 40);

define('QR_BUFFER_LEN', 1024);

// Required lib files
require_once __DIR__ . '/qrconst.php';
require_once __DIR__ . '/qrconfig.php';
require_once __DIR__ . '/qrspec.php';
require_once __DIR__ . '/qrimage.php';
require_once __DIR__ . '/qrinput.php';
require_once __DIR__ . '/qrbitstream.php';
require_once __DIR__ . '/qrsplit.php';
require_once __DIR__ . '/qrrscode.php';
require_once __DIR__ . '/qrmask.php';
require_once __DIR__ . '/qrencode.php';

// Encoding modes
function QRencode($text, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false, $back_color = 0xFFFFFF, $fore_color = 0x000000) {
    $enc = QRencode::factory($level, $size, $margin, $back_color, $fore_color);
    return $enc->encode($text, $saveandprint);
}

// PNG output
function QRcodePNG($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false, $back_color = 0xFFFFFF, $fore_color = 0x000000) {
    $enc = QRencode::factory($level, $size, $margin, $back_color, $fore_color);
    return $enc->encodePNG($text, $outfile, $saveandprint);
}

// JPG output
function QRcodeJPG($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false, $quality = 85, $back_color = 0xFFFFFF, $fore_color = 0x000000) {
    $enc = QRencode::factory($level, $size, $margin, $back_color, $fore_color);
    return $enc->encodeJPG($text, $outfile, $quality, $saveandprint);
}

// EPS output
function QRcodeEPS($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false) {
    $enc = QRencode::factory($level, $size, $margin);
    return $enc->encodeEPS($text, $outfile, $saveandprint);
}

// SVG output
function QRcodeSVG($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false) {
    $enc = QRencode::factory($level, $size, $margin);
    return $enc->encodeSVG($text, $outfile, $saveandprint);
}

// ASCII output
function QRcodeASCII($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint=false) {
    $enc = QRencode::factory($level, $size, $margin);
    return $enc->encodeASCII($text, $outfile, $saveandprint);
}

// UTF-8 to ISO-8859-1 conversion
function QRtools::utf8ToIsolatin1($str)
{
    $utf8 = $str;
    $out = '';
    for ($i = 0; $i < strlen($utf8); $i++) {
        $c = ord($utf8[$i]);
        if ($c <= 0x7F) {
            $out .= chr($c);
        } else if ($c >= 0xC0 && $c <= 0xDF) {
            $c2 = ord($utf8[$i+1]);
            $out .= chr( ((($c & 0x1F) << 6) | ($c2 & 0x3F)) );
            $i++; 
        } else