<?php
// Usage example:
json_decode_sane("['This is valid javascript, but invalid JSON']");

function json_decode_sane(string $s) {
  $r=json_decode($s,true); 
  if ($errCode=json_last_error()) { 
    $errs=findJsonErrors($s);
    throw new Exception('json_decode failed: '.json_last_error_msg()."\n".json_encode($errs[0],JSON_PRETTY_PRINT)); 
  }
  return $r;
}
function findJsonErrors($s) {
  $issues = [];
  $lines = explode("\n", $s);
  $len = strlen($s);
  $pos = 0;
  $lineNum = 1;
  $charPos = 1;
  $stack = []; // Tracks open '{', '['
  $state = 'value'; // 'value', 'key', 'colon', 'comma', 'close'

  // Helper: Add an issue
  $addIssue = function($lineNum, $charPos, $line, $context, $msg) use (&$issues) {
    $issues[] = [
      'line' => $lineNum,
      'char' => $charPos,
      'line_content' => $line,
      'context' => $context,
      'message' => $msg
    ];
  };

  // Helper: Skip whitespace
  $skipWhitespace = function() use ($s, &$pos, &$lineNum, &$charPos, $len) {
    while ($pos < $len && in_array($s[$pos], [' ', "\t", "\n", "\r"])) {
      if (++$_i>100E6) {ex("apparently infinite loop in ckJson");}
      if ($s[$pos] === "\n") {
        $lineNum++;
        $charPos = 1;
      } else {
        $charPos++;
      }
      $pos++;
    }
  };

  // Helper: Parse string
  $parseString = function() use ($s, &$pos, &$lineNum, &$charPos, $lines, $len, $addIssue) {
    $startLine = $lineNum;
    $startChar = $charPos;
    $startPos = $pos;
    $quote = $s[$pos];
    if ($quote !== '"') {
      $addIssue($lineNum, $charPos, $lines[$lineNum-1], substr($s, max(0, $pos-5), 10), "Expected double quote for string");
      return false;
    }
    $pos++;
    $charPos++;
    while ($pos < $len && $s[$pos] !== '"') {
      if (++$_i>100E6) {ex("apparently infinite loop in ckJson");}
      $char = $s[$pos];
      if ($char === "\n") {
        $addIssue($startLine, $startChar, $lines[$startLine-1], substr($s, max(0, $startPos-5), 10), "Unclosed string");
        return false;
      }
      if ($char === '\\') {
        $pos++;
        $charPos++;
        if ($pos >= $len) {
          $addIssue($startLine, $startChar, $lines[$startLine-1], substr($s, max(0, $startPos-5), 10), "Incomplete escape sequence");
          return false;
        }
        $esc = $s[$pos];
        if (!in_array($esc, ['"', '\\', '/', 'b', 'f', 'n', 'r', 't', 'u'])) {
          $addIssue($lineNum, $charPos, $lines[$lineNum-1], substr($s, max(0, $pos-5), 10), "Invalid escape character: \\$esc");
          return false;
        }
        if ($esc === 'u') {
          // TODO: Validate \uXXXX (four hex digits, valid Unicode)
          for ($i = 0; $i < 4; $i++) {
            $pos++;
            $charPos++;
            if ($pos >= $len || !ctype_xdigit($s[$pos])) {
              $addIssue($lineNum, $charPos, $lines[$lineNum-1], substr($s, max(0, $pos-5), 10), "Invalid Unicode escape sequence");
              return false;
            }
          }
        }
      } elseif (ord($char) < 32) {
        $addIssue($lineNum, $charPos, $lines[$lineNum-1], substr($s, max(0, $pos-5), 10), "Unescaped control character");
        return false;
      }
      $pos++;
      $charPos++;
    }
    if ($pos >= $len) {
      $addIssue($startLine, $startChar, $lines[$startLine-1], substr($s, max(0, $startPos-5), 10), "Unclosed string");
      return false;
    }
    $pos++; // Skip closing quote
    $charPos++;
    return true;
  };

  // Helper: Parse number
  $parseNumber = function() use ($s, &$pos, &$lineNum, &$charPos, $lines, $len, $addIssue) {
    $startPos = $pos;
    $startLine = $lineNum;
    $startChar = $charPos;
    $num = '';
    // JSON number: -?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][+-]?[0-9]+)?
    if ($s[$pos] === '-') {
      $num .= $s[$pos];
      $pos++;
      $charPos++;
    }
    if ($pos < $len && $s[$pos] === '0') {
      $num .= '0';
      $pos++;
      $charPos++;
    } elseif ($pos < $len && ctype_digit($s[$pos]) && $s[$pos] !== '0') {
      while ($pos < $len && ctype_digit($s[$pos])) {
        $num .= $s[$pos];
        $pos++;
        $charPos++;
      }
    } else {
      $addIssue($startLine, $startChar, $lines[$startLine-1], substr($s, max(0, $startPos-5), 10), "Invalid number: expected digit");
      return false;
    }
    if ($pos < $len && $s[$pos] === '.') {
      $num .= '.';
      $pos++;
      $charPos++;
      if ($pos >= $len || !ctype_digit($s[$pos])) {
        $addIssue($startLine, $startChar, $lines[$startLine-1], substr($s, max(0, $startPos-5), 10), "Invalid number: expected digits after decimal");
        return false;
      }
      while ($pos < $len && ctype_digit($s[$pos])) {
        $num .= $s[$pos];
        $pos++;
        $charPos++;
      }
    }
    if ($pos < $len && in_array($s[$pos], ['e', 'E'])) {
      $num .= $s[$pos];
      $pos++;
      $charPos++;
      if ($pos < $len && in_array($s[$pos], ['+', '-'])) {
        $num .= $s[$pos];
        $pos++;
        $charPos++;
      }
      if ($pos >= $len || !ctype_digit($s[$pos])) {
        $addIssue($startLine, $startChar, $lines[$startLine-1], substr($s, max(0, $startPos-5), 10), "Invalid number: expected digits after exponent");
        return false;
      }
      while ($pos < $len && ctype_digit($s[$pos])) {
        $num .= $s[$pos];
        $pos++;
        $charPos++;
      }
    }
    if (!preg_match('/^-?(0|[1-9][0-9]*)(\.[0-9]+)?([eE][+-]?[0-9]+)?$/', $num)) {
      $addIssue($startLine, $startChar, $lines[$startLine-1], substr($s, max(0, $startPos-5), 10), "Invalid number format: $num");
      return false;
    }
    return true;
  };

  // Main parsing loop
  while ($pos < $len) {
    if (++$_i>100E6) {ex("apparently infinite loop in ckJson");}
    $skipWhitespace();
    if ($pos >= $len) break;

    $char = $s[$pos];
    $line = $lines[$lineNum-1];
    $context = substr($s, max(0, $pos-5), 10);

    if ($state === 'value') {
      if ($char === '{') {
        $stack[] = '{';
        $state = 'key';
        $pos++;
        $charPos++;
      } elseif ($char === '[') {
        $stack[] = '[';
        $state = 'value';
        $pos++;
        $charPos++;
      } elseif ($char === '"') {
        if ($parseString()) {
          $state = count($stack) ? (end($stack) === '{' ? 'colon' : 'comma') : '';
        } else {
          $state = count($stack) ? 'comma' : ''; // Recover to continue parsing
        }
      } elseif ($char === "'") {
        $addIssue($lineNum, $charPos, $line, $context, "Single quotes are not allowed in JSON");
        $startLine = $lineNum;
        $startChar = $charPos;
        $startPos = $pos;
        $pos++;
        $charPos++;
        while ($pos < $len && $s[$pos] !== "'") {
          if ($s[$pos] === "\n") {
            $lineNum++;
            $charPos = 1;
          } else {
            $charPos++;
          }
          $pos++;
        }
        if ($pos < $len) {
          $pos++; // Skip closing single quote
          $charPos++;
        }
        $state = count($stack) ? 'comma' : '';
      } elseif ($char === '-' || ctype_digit($char)) {
        if ($parseNumber()) {
          $state = count($stack) ? 'comma' : '';
        } else {
          $state = count($stack) ? 'comma' : ''; // Recover
        }
      } elseif (substr($s, $pos, 4) === 'true') {
        $pos += 4;
        $charPos += 4;
        $state = count($stack) ? 'comma' : '';
      } elseif (substr($s, $pos, 5) === 'false') {
        $pos += 5;
        $charPos += 5;
        $state = count($stack) ? 'comma' : '';
      } elseif (substr($s, $pos, 4) === 'null') {
        $pos += 4;
        $charPos += 4;
        $state = count($stack) ? 'comma' : '';
      } else {
        $addIssue($lineNum, $charPos, $line, $context, "Unexpected character: $char");
        $pos++;
        $charPos++;
        $state = count($stack) ? 'comma' : '';
      }
    } elseif ($state === 'key') {
      if ($char === '"') {
        if ($parseString()) {
          $state = 'colon';
        } else {
          $state = 'colon'; // Recover
        }
      } elseif ($char === '}' && end($stack) === '{') {
        array_pop($stack);
        $state = count($stack) ? (end($stack) === '{' ? 'comma' : 'comma') : '';
        $pos++;
        $charPos++;
      } else {
        $addIssue($lineNum, $charPos, $line, $context, "Expected string key or closing brace");
        $pos++;
        $charPos++;
        $state = 'colon';
      }
    } elseif ($state === 'colon') {
      if ($char === ':') {
        $state = 'value';
        $pos++;
        $charPos++;
      } else {
        $addIssue($lineNum, $charPos, $line, $context, "Expected colon after key");
        $pos++;
        $charPos++;
        $state = 'value';
      }
    } elseif ($state === 'comma') {
      if ($char === ',') {
        $state = end($stack) === '{' ? 'key' : 'value';
        $pos++;
        $charPos++;
      } elseif ($char === '}' && end($stack) === '{') {
        array_pop($stack);
        $state = count($stack) ? (end($stack) === '{' ? 'comma' : 'comma') : '';
        $pos++;
        $charPos++;
      } elseif ($char === ']' && end($stack) === '[') {
        array_pop($stack);
        $state = count($stack) ? (end($stack) === '{' ? 'comma' : 'comma') : '';
        $pos++;
        $charPos++;
      } else {
        $addIssue($lineNum, $charPos, $line, $context, "Expected comma or closing bracket");
        $pos++;
        $charPos++;
        $state = end($stack) === '{' ? 'key' : 'value';
      }
    }
  }

  // Check for unclosed structures
  if (count($stack)) {
    $addIssue($lineNum, $charPos, $lines[$lineNum-1], substr($s, max(0, $pos-5), 10), "Unclosed " . end($stack));
  } elseif ($state !== '' && $state !== 'comma') {
    $addIssue($lineNum, $charPos, $lines[$lineNum-1], substr($s, max(0, $pos-5), 10), "Incomplete JSON structure");
  }

  return $issues;
}

