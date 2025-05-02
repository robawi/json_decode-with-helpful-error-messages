Super easy to use: just put the file json_decode_sane.php into your code base and include it (require_once('json_decode_sane.php') or copy the functions json_decode_sane and findJsonErrors to your php script.
Then just call json_decode_sane($s), where $s is the JSON string you want to parse. It will return the parse result, just as json_decode does, or throw an exception with helpful info when parsing failed.
The info in the exception message comprises a useful explanation of the error, the exact line number and character position of the error location, the text of the line containing the error and a short context of the error to give you fast, precise info.

As a bonus, you can also use the function findJsonErrors($s) diretly for syntax analysis of a JSON string. It returns an array of all errors found (the Exception from json_decode_sane provides only the first error location; anyway that's what you are usually interested in and we want to keep the Exception concise).

json_decode_sane calls the native json_deocde and only in case this fails it calls findJsonErrors to get the error info. Thus for good JSON strings the parsing remains fast and only in case of errors the slower error parsing is done.
