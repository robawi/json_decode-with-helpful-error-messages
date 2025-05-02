Super easy to use:
Just place the file json_decode_sane.php into your codebase and include it with:
require_once('json_decode_sane.php');
Alternatively, you can copy the functions json_decode_sane and findJsonErrors directly into your script.

Then, simply call:
json_decode_sane($s);
Where $s is the JSON string you want to parse. It behaves like json_decode, returning the parsed result, but throws an exception with helpful information if parsing fails.

The exception message includes:
A clear explanation of the error,
The exact line number and character position of the error,
The full line of text where the error occurred,
A short surrounding context, helping you debug quickly and precisely.

Bonus:
You can also use findJsonErrors($s) directly to perform a syntax check of a JSON string. It returns an array of all errors found.
(Whereas json_decode_sane() only reports the first error in an exception—usually sufficient and more concise.)

Performance:
json_decode_sane() calls native json_decode() first, and only falls back to findJsonErrors() if parsing fails. This ensures negligible overhead on valid JSON and detailed feedback only when needed.

