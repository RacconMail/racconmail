const fs = require('fs');

let api = fs.readFileSync('public/api.php', 'utf8');

const targetFunctionStart = 'function cleanMimeEmailContent($raw) {';
const targetFunctionEnd = 'function deletePhysicalMaildirFiles(';

const startIdx = api.indexOf(targetFunctionStart);
const endIdx = api.indexOf(targetFunctionEnd);

if (startIdx === -1 || endIdx === -1) {
  console.error('Could not find function boundaries in api.php!');
  process.exit(1);
}

const newFunction = `function cleanMimeEmailContent($raw) {
    if (empty($raw) || !is_string($raw)) return '';
    $str = trim($raw);

    // Detect ZIP or GZIP binary data stored directly in message body (e.g. DMARC automated reports)
    if (str_starts_with($str, "PK\\x03\\x04")) {
        return "[Binary Archive: ZIP file / DMARC Report attachment]";
    }
    if (str_starts_with($str, "\\x1f\\x8b")) {
        return "[Binary Archive: GZIP compressed data attachment]";
    }

    // Strip null bytes
    $str = str_replace("\\0", '', $str);

    // Ensure initial UTF-8 validity
    if (!mb_check_encoding($str, 'UTF-8')) {
        $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    if (str_contains($str, '--') || preg_match('/content-type:\\s*(text\\/plain|text\\/html|multipart)/i', $str)) {
        if (preg_match('/content-type:\\s*text\\/plain[^\\r\\n]*\\r?\\n(?:[^\\r\\n]+\\r?\\n)*\\r?\\n([\\s\\S]*?)(?=--[a-zA-Z0-9_\\-\\.]+|\\z)/i', $str, $pm)) {
            $candidate = trim($pm[1]);
            if (!empty($candidate)) {
                $str = $candidate;
            }
        } elseif (preg_match('/content-type:\\s*text\\/html[^\\r\\n]*\\r?\\n(?:[^\\r\\n]+\\r?\\n)*\\r?\\n([\\s\\S]*?)(?=--[a-zA-Z0-9_\\-\\.]+|\\z)/i', $str, $hm)) {
            $candidate = trim($hm[1]);
            if (!empty($candidate)) {
                $str = trim(strip_tags($candidate));
            }
        } else {
            $str = preg_replace('/--[a-zA-Z0-9_\\-\\.]+(?:--)?/', '', $str);
            $str = preg_replace('/content-type:[^\\r\\n]+/i', '', $str);
            $str = preg_replace('/content-transfer-encoding:[^\\r\\n]+/i', '', $str);
            $str = preg_replace('/charset=["\\']?[^"\\\'\\r\\n]+["\\\']?/i', '', $str);
        }
    }

    $str = preg_replace('/^--[a-zA-Z0-9_\\-\\.]+/m', '', $str);
    $str = preg_replace('/--[a-zA-Z0-9_\\-\\.]+--\\s*$/m', '', $str);
    $str = preg_replace('/^[ \\t]*Content-Type:[^\\r\\n]*/mi', '', $str);
    $str = preg_replace('/^[ \\t]*Content-Transfer-Encoding:[^\\r\\n]*/mi', '', $str);

    if (str_contains($str, '=20') || str_contains($str, '=0A') || str_contains($str, "=\\r\\n")) {
        $str = quoted_printable_decode($str);
    }

    if (!mb_check_encoding($str, 'UTF-8')) {
        $str = @iconv('UTF-8', 'UTF-8//IGNORE', $str) ?: '';
    }

    return trim($str);
}

`;

api = api.substring(0, startIdx) + newFunction + api.substring(endIdx);

// Also patch line 2542 where json_encode(['messages' => $messages]) is called
const oldJsonEncode = "echo json_encode(['messages' => $messages]);";
const newJsonEncode = `$jsonOut = json_encode(['messages' => $messages], JSON_INVALID_UTF8_SUBSTITUTE);
        if ($jsonOut === false) {
            $jsonOut = '{"messages":[]}';
        }
        echo $jsonOut;`;

if (api.includes(oldJsonEncode)) {
  api = api.replace(oldJsonEncode, newJsonEncode);
  console.log('Replaced old json_encode with robust JSON_INVALID_UTF8_SUBSTITUTE version!');
} else {
  console.log('Target json_encode already updated or different.');
}

fs.writeFileSync('public/api.php', api, 'utf8');
console.log('Successfully patched public/api.php!');
