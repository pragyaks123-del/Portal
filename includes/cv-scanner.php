<?php

declare(strict_types=1);

// Dev 2: CV Scan - coordinates API parsing, local text extraction, and job matching.
function scanResumeFile(string $path, string $extension, string $manualSummary = '', string $manualSkills = ''): array
{
    $startedAt = time();
    $apiScan = parseResumeWithCvParserApi($path, $manualSummary, $manualSkills, $startedAt);

    if ($apiScan !== null) {
        return $apiScan;
    }

    $text = extractResumeText($path, $extension);
    $text = cleanParsedText($text);

    if ($text === '') {
        return [
            'status' => 'failed',
            'error' => 'We could not read your CV - please upload a text-based PDF, DOCX, or a clear image CV.',
            'parsed_text' => '',
            'summary' => $manualSummary,
            'skills' => $manualSkills,
            'extracted_skills' => '',
            'job_titles' => '',
            'years_experience' => 0,
            'education' => '',
            'qualifications' => '',
            'duration' => time() - $startedAt,
        ];
    }

    $fields = extractResumeFields($text, $manualSkills);

    return [
        'status' => 'completed',
        'error' => '',
        'parsed_text' => mb_substr($text, 0, 65000),
        'summary' => $manualSummary !== '' ? $manualSummary : buildResumeSummary($text, $fields),
        'skills' => $manualSkills !== '' ? $manualSkills : implode(', ', $fields['skills']),
        'extracted_skills' => implode(', ', $fields['skills']),
        'job_titles' => implode(', ', $fields['job_titles']),
        'years_experience' => $fields['years_experience'],
        'education' => implode("\n", $fields['education']),
        'qualifications' => implode("\n", $fields['qualifications']),
        'duration' => time() - $startedAt,
    ];
}

function cvParserConfig(): array
{
    $config = $GLOBALS['cvparser_config'] ?? [];

    return is_array($config) ? $config : [];
}

function parseResumeWithCvParserApi(string $path, string $manualSummary, string $manualSkills, int $startedAt): ?array
{
    $config = cvParserConfig();
    $apiKeys = cvParserApiKeys($config);
    $endpoint = trim((string) ($config['endpoint'] ?? 'https://cvparser.ai/api/v4/parse'));

    // Dev 2: Fall back to local parsing when the external CV parser is disabled or unavailable.
    if (($config['enabled'] ?? false) !== true || $apiKeys === [] || $endpoint === '' || !function_exists('curl_init')) {
        return null;
    }

    $fileContents = @file_get_contents($path);
    if (!is_string($fileContents) || $fileContents === '') {
        return null;
    }

    $payload = json_encode(['base64' => base64_encode($fileContents)]);
    if (!is_string($payload)) {
        return null;
    }

    foreach ($apiKeys as $apiKey) {
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int) ($config['connect_timeout'] ?? 3),
            CURLOPT_TIMEOUT => (int) ($config['timeout'] ?? 25),
        ]);

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($response) || $response === '' || $statusCode < 200 || $statusCode >= 300) {
            logCvParserEvent('cvparser.ai request failed.', [
                'status_code' => $statusCode,
                'curl_error' => $curlError,
                'response_preview' => is_string($response) ? mb_substr($response, 0, 220) : '',
            ]);
            continue;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            logCvParserEvent('cvparser.ai returned invalid JSON.', [
                'status_code' => $statusCode,
                'response_preview' => mb_substr($response, 0, 220),
            ]);
            continue;
        }

        $apiFields = extractCvParserApiFields($decoded);
        $text = cleanParsedText($apiFields['text']);

        if ($text === '' && !$apiFields['skills'] && !$apiFields['job_titles'] && !$apiFields['education']) {
            logCvParserEvent('cvparser.ai returned no readable resume fields.', [
                'status_code' => $statusCode,
                'response_preview' => mb_substr($response, 0, 220),
            ]);
            continue;
        }

        // Dev 2: Merge API fields with local keyword extraction so matching still works with partial API responses.
        $localFields = extractResumeFields($text, trim($manualSkills . ', ' . implode(', ', $apiFields['skills'])));
        $skills = normalizeSkillList(implode(', ', array_merge($apiFields['skills'], $localFields['skills'])));
        $jobTitles = array_values(array_unique(array_merge($apiFields['job_titles'], $localFields['job_titles'])));
        $education = array_values(array_unique(array_merge($apiFields['education'], $localFields['education'])));
        $qualifications = array_values(array_unique(array_merge($apiFields['qualifications'], $localFields['qualifications'])));
        $yearsExperience = max($apiFields['years_experience'], $localFields['years_experience']);

        $summaryFields = [
            'job_titles' => $jobTitles,
            'years_experience' => $yearsExperience,
            'education' => $education,
        ];

        return [
            'status' => 'completed',
            'error' => '',
            'parsed_text' => mb_substr($text !== '' ? $text : flattenCvParserText($decoded), 0, 65000),
            'summary' => $manualSummary !== '' ? $manualSummary : buildResumeSummary($text !== '' ? $text : flattenCvParserText($decoded), $summaryFields),
            'skills' => $manualSkills !== '' ? $manualSkills : implode(', ', $skills),
            'extracted_skills' => implode(', ', $skills),
            'job_titles' => implode(', ', array_slice($jobTitles, 0, 8)),
            'years_experience' => $yearsExperience,
            'education' => implode("\n", array_slice($education, 0, 6)),
            'qualifications' => implode("\n", array_slice($qualifications, 0, 6)),
            'duration' => time() - $startedAt,
        ];
    }

    return null;
}

function cvParserApiKeys(array $config): array
{
    $keys = [];

    if (isset($config['api_keys']) && is_array($config['api_keys'])) {
        foreach ($config['api_keys'] as $apiKey) {
            $keys[] = trim((string) $apiKey);
        }
    }

    if (isset($config['api_key'])) {
        $keys[] = trim((string) $config['api_key']);
    }

    return array_values(array_unique(array_filter($keys)));
}

function logCvParserEvent(string $message, array $context = []): void
{
    $storageDir = __DIR__ . '/../storage';
    if (!is_dir($storageDir)) {
        return;
    }

    $line = sprintf(
        "[%s] %s %s\n",
        date('c'),
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
    );

    @file_put_contents($storageDir . '/cvparser.log', $line, FILE_APPEND);
}

function extractCvParserApiFields(array $response): array
{
    // Dev 2: Support common response shapes from CV parser services before normalizing the fields.
    $root = $response['data'] ?? $response['result'] ?? $response['resume'] ?? $response['cv'] ?? $response;
    $text = firstStringValue($root, ['text', 'raw_text', 'plain_text', 'content', 'resume_text', 'cv_text']);

    if ($text === '') {
        $text = flattenCvParserText($root);
    }

    return [
        'text' => $text,
        'skills' => normalizeSkillList(implode(', ', collectStringValues($root, ['skills', 'skill']))),
        'job_titles' => collectStringValues($root, ['job_title', 'job_titles', 'position', 'positions', 'title']),
        'years_experience' => firstIntValue($root, ['years_experience', 'total_years_experience', 'experience_years']),
        'education' => collectStringValues($root, ['education', 'educations', 'degree', 'degrees']),
        'qualifications' => collectStringValues($root, ['qualification', 'qualifications', 'certification', 'certifications']),
    ];
}

function firstStringValue(mixed $value, array $keys): string
{
    if (!is_array($value)) {
        return '';
    }

    foreach ($value as $key => $child) {
        $normalizedKey = mb_strtolower((string) $key);
        if (in_array($normalizedKey, $keys, true) && is_scalar($child)) {
            return trim((string) $child);
        }
        if (is_array($child)) {
            $found = firstStringValue($child, $keys);
            if ($found !== '') {
                return $found;
            }
        }
    }

    return '';
}

function firstIntValue(mixed $value, array $keys): int
{
    if (!is_array($value)) {
        return 0;
    }

    foreach ($value as $key => $child) {
        $normalizedKey = mb_strtolower((string) $key);
        if (in_array($normalizedKey, $keys, true) && is_numeric($child)) {
            return (int) $child;
        }
        if (is_array($child)) {
            $found = firstIntValue($child, $keys);
            if ($found > 0) {
                return $found;
            }
        }
    }

    return 0;
}

function collectStringValues(mixed $value, array $keys): array
{
    $values = [];

    collectStringValuesInto($value, array_fill_keys($keys, true), $values);

    return array_values(array_unique(array_filter(array_map('trim', $values))));
}

function collectStringValuesInto(mixed $value, array $keys, array &$values): void
{
    if (!is_array($value)) {
        return;
    }

    foreach ($value as $key => $child) {
        $normalizedKey = mb_strtolower((string) $key);
        if (isset($keys[$normalizedKey])) {
            flattenScalarValues($child, $values);
            continue;
        }

        if (is_array($child)) {
            collectStringValuesInto($child, $keys, $values);
        }
    }
}

function flattenScalarValues(mixed $value, array &$values): void
{
    if (is_scalar($value)) {
        $values[] = (string) $value;
        return;
    }

    if (!is_array($value)) {
        return;
    }

    foreach ($value as $child) {
        flattenScalarValues($child, $values);
    }
}

function flattenCvParserText(mixed $value): string
{
    $values = [];
    flattenScalarValues($value, $values);

    return implode("\n", array_slice(array_filter(array_map('trim', $values)), 0, 300));
}

function extractResumeText(string $path, string $extension): string
{
    // Dev 2: Route each uploaded resume format to the best available text extractor.
    return match ($extension) {
        'pdf' => extractPdfText($path),
        'docx' => extractDocxText($path),
        'png', 'jpg', 'jpeg', 'webp', 'tif', 'tiff' => extractImageText($path),
        default => '',
    };
}

function extractPdfText(string $path): string
{
    if (class_exists(\Smalot\PdfParser\Parser::class)) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();

            if (trim($text) !== '') {
                return $text;
            }
        } catch (Throwable $exception) {
            logCvParserEvent('smalot/pdfparser could not read PDF.', [
                'error' => $exception->getMessage(),
                'file' => basename($path),
            ]);
        }
    }

    if (commandExists('pdftotext')) {
        $output = shell_exec('pdftotext -layout ' . escapeshellarg($path) . ' - 2>NUL');
        if (is_string($output) && trim($output) !== '') {
            return $output;
        }
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return '';
    }

    preg_match_all('/\((?:\\\\.|[^\\\\()])*\)\s*Tj|\[(.*?)\]\s*TJ/s', $raw, $matches);
    $chunks = [];

    foreach ($matches[0] as $match) {
        preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $match, $strings);
        foreach ($strings[0] as $pdfString) {
            $chunks[] = decodePdfString(substr($pdfString, 1, -1));
        }
    }

    return implode(' ', $chunks);
}

function extractDocxText(string $path): string
{
    if (class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
            $text = phpWordDocumentToText($phpWord);

            if (trim($text) !== '') {
                return $text;
            }
        } catch (Throwable $exception) {
            logCvParserEvent('phpoffice/phpword could not read DOCX.', [
                'error' => $exception->getMessage(),
                'file' => basename($path),
            ]);
        }
    }

    $parts = ['word/document.xml', 'word/header1.xml', 'word/footer1.xml'];
    $text = '';

    if (class_exists(ZipArchive::class)) {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        foreach ($parts as $part) {
            $xml = $zip->getFromName($part);
            if (is_string($xml)) {
                $text .= ' ' . docxXmlToText($xml);
            }
        }

        $zip->close();

        return $text;
    }

    $entries = readZipEntries($path, $parts);
    foreach ($entries as $xml) {
        $text .= ' ' . docxXmlToText($xml);
    }

    return $text;
}

function phpWordDocumentToText(object $phpWord): string
{
    $lines = [];

    foreach ($phpWord->getSections() as $section) {
        phpWordElementsToText($section->getElements(), $lines);
    }

    return implode("\n", array_filter(array_map('trim', $lines)));
}

function phpWordElementsToText(array $elements, array &$lines): void
{
    foreach ($elements as $element) {
        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_scalar($text)) {
                $lines[] = (string) $text;
            }
        }

        if (method_exists($element, 'getElements')) {
            phpWordElementsToText($element->getElements(), $lines);
        }

        if (method_exists($element, 'getRows')) {
            foreach ($element->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    phpWordElementsToText($cell->getElements(), $lines);
                }
            }
        }
    }
}

function docxXmlToText(string $xml): string
{
    $xml = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);

    return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function readZipEntries(string $path, array $wantedNames): array
{
    $data = @file_get_contents($path);
    if (!is_string($data) || $data === '') {
        return [];
    }

    $wanted = array_fill_keys($wantedNames, true);
    $offset = 0;
    $entries = [];
    $length = strlen($data);

    while ($offset + 30 < $length) {
        $signature = substr($data, $offset, 4);
        if ($signature !== "PK\x03\x04") {
            $next = strpos($data, "PK\x03\x04", $offset + 1);
            if ($next === false) {
                break;
            }
            $offset = $next;
            continue;
        }

        $header = unpack(
            'vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLength/vextraLength',
            substr($data, $offset + 4, 26)
        );

        if (!$header) {
            break;
        }

        $nameStart = $offset + 30;
        $name = substr($data, $nameStart, (int) $header['nameLength']);
        $bodyStart = $nameStart + (int) $header['nameLength'] + (int) $header['extraLength'];
        $bodyLength = (int) $header['compressed'];

        if ($bodyStart + $bodyLength > $length) {
            break;
        }

        if (isset($wanted[$name])) {
            $body = substr($data, $bodyStart, $bodyLength);
            if ((int) $header['method'] === 8) {
                $body = @gzinflate($body);
            }
            if (is_string($body)) {
                $entries[$name] = $body;
            }
        }

        $offset = $bodyStart + $bodyLength;
    }

    return $entries;
}

function extractImageText(string $path): string
{
    if (!commandExists('tesseract')) {
        return '';
    }

    $output = shell_exec('tesseract ' . escapeshellarg($path) . ' stdout --psm 6 2>NUL');

    return is_string($output) ? $output : '';
}

function commandExists(string $command): bool
{
    $probe = PHP_OS_FAMILY === 'Windows'
        ? 'where ' . escapeshellarg($command) . ' 2>NUL'
        : 'command -v ' . escapeshellarg($command) . ' 2>/dev/null';

    $output = shell_exec($probe);

    return is_string($output) && trim($output) !== '';
}

function decodePdfString(string $value): string
{
    $value = preg_replace('/\\\\([nrtbf()\\\\])/', ' ', $value) ?? $value;
    $value = preg_replace('/\\\\[0-7]{1,3}/', ' ', $value) ?? $value;

    return $value;
}

function cleanParsedText(string $text): string
{
    $text = preg_replace('/[^\P{C}\r\n\t]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace("/(\r?\n){3,}/", "\n\n", $text) ?? $text;

    return trim($text);
}

function extractResumeFields(string $text, string $manualSkills = ''): array
{
    // Dev 2: Lightweight local field detection used when API parsing is skipped or incomplete.
    $skillVocabulary = [
        'PHP', 'Laravel', 'JavaScript', 'TypeScript', 'React', 'Vue', 'Angular', 'Node.js', 'HTML',
        'CSS', 'SQL', 'MySQL', 'PostgreSQL', 'APIs', 'REST', 'Git', 'Figma', 'Branding', 'UI',
        'UX', 'Excel', 'CRM', 'Sales', 'Communication', 'Leadership', 'Risk', 'Strategy',
        'Planning', 'Process', 'Stakeholders', 'Collaboration', 'Python', 'Java', 'C#',
        'Project Management', 'Customer Service', 'Accounting', 'Marketing', 'SEO',
    ];
    $skills = normalizeSkillList($manualSkills);
    $lowerText = mb_strtolower($text);

    foreach ($skillVocabulary as $skill) {
        $pattern = '/(?<![a-z0-9])' . preg_quote(mb_strtolower($skill), '/') . '(?![a-z0-9])/u';
        if (preg_match($pattern, $lowerText)) {
            $skills[] = $skill;
        }
    }

    preg_match_all('/(\d{1,2})\+?\s*(?:years|yrs)\s+(?:of\s+)?experience/i', $text, $yearMatches);
    $years = $yearMatches[1] ? max(array_map('intval', $yearMatches[1])) : 0;

    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $jobTitles = [];
    $education = [];
    $qualifications = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || mb_strlen($line) > 180) {
            continue;
        }

        if (preg_match('/\b(developer|engineer|designer|manager|executive|director|analyst|coordinator|planner|facilitator|accountant|consultant|specialist)\b/i', $line)) {
            $jobTitles[] = $line;
        }

        if (preg_match('/\b(bachelor|master|degree|university|college|school|bsc|msc|mba|bba|diploma)\b/i', $line)) {
            $education[] = $line;
        }

        if (preg_match('/\b(certified|certification|qualification|training|license|licence)\b/i', $line)) {
            $qualifications[] = $line;
        }
    }

    return [
        'skills' => array_values(array_unique($skills)),
        'job_titles' => array_slice(array_values(array_unique($jobTitles)), 0, 6),
        'years_experience' => $years,
        'education' => array_slice(array_values(array_unique($education)), 0, 5),
        'qualifications' => array_slice(array_values(array_unique($qualifications)), 0, 5),
    ];
}

function normalizeSkillList(string $skills): array
{
    $parts = preg_split('/[,;|\n]+/', $skills) ?: [];
    $normalized = [];
    $seen = [];

    foreach ($parts as $part) {
        $skill = canonicalSkillLabel(trim($part));
        $key = skillKey($skill);
        if ($skill !== '' && !isset($seen[$key])) {
            $normalized[] = $skill;
            $seen[$key] = true;
        }
    }

    return $normalized;
}

function skillKey(string $skill): string
{
    $key = mb_strtolower(trim($skill));
    $key = str_replace(['+', '#'], ['plus', 'sharp'], $key);

    return preg_replace('/[^a-z0-9]+/', '', $key) ?? $key;
}

function canonicalSkillLabel(string $skill): string
{
    $skill = trim($skill);
    $aliases = [
        'api' => 'APIs',
        'apis' => 'APIs',
        'css' => 'CSS',
        'crm' => 'CRM',
        'html' => 'HTML',
        'javascript' => 'JavaScript',
        'mysql' => 'MySQL',
        'nodejs' => 'Node.js',
        'php' => 'PHP',
        'postgresql' => 'PostgreSQL',
        'rest' => 'REST',
        'seo' => 'SEO',
        'sql' => 'SQL',
        'ui' => 'UI',
        'ux' => 'UX',
    ];
    $key = skillKey($skill);

    return $aliases[$key] ?? $skill;
}

function skillAppearsInText(string $skill, string $text): bool
{
    if (trim($skill) === '' || trim($text) === '') {
        return false;
    }

    $pattern = '/(?<![a-z0-9])' . preg_quote(mb_strtolower($skill), '/') . '(?![a-z0-9])/u';
    if (preg_match($pattern, mb_strtolower($text))) {
        return true;
    }

    $plainSkill = skillKey($skill);
    $plainText = preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($text)) ?? '';
    $plainPattern = '/(?<![a-z0-9])' . preg_quote($plainSkill, '/') . '(?![a-z0-9])/';

    return $plainSkill !== '' && preg_match($plainPattern, $plainText) === 1;
}

function buildResumeSummary(string $text, array $fields): string
{
    $summary = [];
    if ($fields['job_titles']) {
        $summary[] = 'Detected roles: ' . implode(', ', array_slice($fields['job_titles'], 0, 3)) . '.';
    }
    if ($fields['years_experience'] > 0) {
        $summary[] = 'Experience: ' . $fields['years_experience'] . '+ years.';
    }
    if ($fields['education']) {
        $summary[] = 'Education: ' . $fields['education'][0] . '.';
    }

    if ($summary) {
        return implode(' ', $summary);
    }

    return mb_substr(preg_replace('/\s+/', ' ', $text) ?? $text, 0, 260);
}

function resumeMatchForJob(?array $resume, array $job): ?array
{
    if (!$resume || ($resume['scan_status'] ?? 'completed') !== 'completed') {
        return null;
    }

    // Dev 2: Compare normalized resume skills against job skills and include experience in the score.
    $resumeSkills = normalizeSkillList(trim((string) ($resume['extracted_skills'] ?? '') . ', ' . (string) ($resume['skills'] ?? '')));
    $jobSkills = normalizeSkillList((string) $job['skills']);
    $resumeSkillMap = array_fill_keys(array_map('skillKey', $resumeSkills), true);
    $resumeSearchText = implode(' ', [
        (string) ($resume['parsed_text'] ?? ''),
        (string) ($resume['summary'] ?? ''),
        (string) ($resume['job_titles'] ?? ''),
        (string) ($resume['education'] ?? ''),
        (string) ($resume['qualifications'] ?? ''),
        (string) ($resume['skills'] ?? ''),
        (string) ($resume['extracted_skills'] ?? ''),
    ]);
    $matchedSkills = [];
    $missingSkills = [];

    foreach ($jobSkills as $skill) {
        $key = skillKey($skill);
        if (isset($resumeSkillMap[$key]) || skillAppearsInText($skill, $resumeSearchText)) {
            $matchedSkills[] = $skill;
        } else {
            $missingSkills[] = $skill;
        }
    }

    $requiredYears = detectRequiredYears((string) $job['description'] . ' ' . (string) $job['skills']);
    $resumeYears = (int) ($resume['years_experience'] ?? 0);
    $experienceMatched = $requiredYears === 0 || $resumeYears >= $requiredYears;
    $skillScore = $jobSkills ? (count($matchedSkills) / count($jobSkills)) * 80 : 80;
    $experienceScore = $experienceMatched ? 20 : 0;
    $score = (int) round(min(100, $skillScore + $experienceScore));
    $matchedRequirements = $matchedSkills;
    $missingRequirements = $missingSkills;

    if ($requiredYears > 0) {
        if ($experienceMatched) {
            $matchedRequirements[] = $requiredYears . '+ years of experience';
        } else {
            $missingRequirements[] = $requiredYears . ' years of experience';
        }
    }

    return [
        'score' => $score,
        'matched_skills' => $matchedSkills,
        'missing_skills' => $missingSkills,
        'matched_requirements' => $matchedRequirements,
        'missing_requirements' => $missingRequirements,
        'required_years' => $requiredYears,
    ];
}

function detectRequiredYears(string $text): int
{
    preg_match_all('/(\d{1,2})\+?\s*(?:years|yrs)\s+(?:of\s+)?experience/i', $text, $matches);

    return $matches[1] ? max(array_map('intval', $matches[1])) : 0;
}
