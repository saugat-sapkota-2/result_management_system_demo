<?php
/**
 * RMS -- Central Nepali Bikram Sambat (BS) date service.
 *
 * Single source of truth for everything date-related in the UI.
 * The database keeps Gregorian (AD) DATE/DATETIME values and all SQL
 * calculations stay AD. This service only converts at the input/output
 * boundary:
 *
 *   User input (BS)  ->  bsToAd()  ->  database (AD)
 *   Database (AD)    ->  adToBs()  ->  user display (BS)
 *
 * Calendar dataset/algorithm:
 *   Based on the Apache-2.0 "bikram-sambat" converter
 *   (https://github.com/sushiljic/bikram-sambat, a fork of medic/bikram-sambat).
 *   Epoch: BS 1970-01-01 = AD 1913-04-13, month lengths encoded per BS year.
 *   This is a real Nepali-panchang-backed calendar (not "AD + 56/57").
 *
 * Supported range: BS 1970..2090 (AD ~1913..2033) -- enough for DOBs,
 * current academic dates and future exam dates.
 */

if (!defined('BS_YEAR_ZERO')) {
    define('BS_YEAR_ZERO', 1970);
    define('BS_EPOCH_AD', '1913-04-13');
    // Each entry encodes the 12 month lengths for one BS year as 2 bits per
    // month (0..3 added to a base of 29 days). Month 1 = least significant pair.
    define('BS_ENCODED_MONTH_LENGTHS', [
        5315258, 5314490, 9459438, 8673005, 5315258, 5315066, 9459438, 8673005,
        5315258, 5314298, 9459438, 5327594, 5315258, 5314298, 9459438, 5327594,
        5315258, 5314286, 9459438, 5315306, 5315258, 5314286, 8673006, 5315306,
        5315258, 5265134, 8673006, 5315258, 5315258, 9459438, 8673005, 5315258,
        5314298, 9459438, 8673005, 5315258, 5314298, 9459438, 8473322, 5315258,
        5314298, 9459438, 5327594, 5315258, 5314298, 9459438, 5327594, 5315258,
        5314286, 8673006, 5315306, 5315258, 5265134, 8673006, 5315306, 5315258,
        9459438, 8673005, 5315258, 5314490, 9459438, 8673005, 5315258, 5314298,
        9459438, 8473325, 5315258, 5314298, 9459438, 5327594, 5315258, 5314298,
        9459438, 5327594, 5315258, 5314286, 9459438, 5315306, 5315258, 5265134,
        8673006, 5315306, 5315258, 5265134, 8673006, 5315258, 5314490, 9459438,
        8673005, 5315258, 5314298, 9459438, 8669933, 5315258, 5314298, 9459438,
        8473322, 5315258, 5314298, 9459438, 5327594, 5315258, 5314286, 9459438,
        5315306, 5315258, 5265134, 8673006, 5315306, 5315258, 5265134, 5527290,
        5527277, 5527226, 5527226, 5528046, 5527277, 5528250, 5528057, 5527277,
        5527277,
    ]);
}

/** Nepali BS month names (English transliteration), 1 = Baisakh. */
function bs_month_names(): array
{
    return [
        1 => 'Baisakh', 2 => 'Jestha', 3 => 'Ashadh', 4 => 'Shrawan',
        5 => 'Bhadra', 6 => 'Ashwin', 7 => 'Kartik', 8 => 'Mangsir',
        9 => 'Poush', 10 => 'Magh', 11 => 'Falgun', 12 => 'Chaitra',
    ];
}

/**
 * Number of days in a BS month.
 * @throws InvalidArgumentException outside supported range
 */
function bs_days_in_month(int $year, int $month): int
{
    if ($month < 1 || $month > 12) {
        throw new InvalidArgumentException("Invalid BS month: {$month}");
    }
    $idx = $year - BS_YEAR_ZERO;
    if ($idx < 0 || !isset(BS_ENCODED_MONTH_LENGTHS[$idx])) {
        throw new InvalidArgumentException("BS year {$year} is outside the supported calendar range.");
    }
    $encoded = BS_ENCODED_MONTH_LENGTHS[$idx];
    return 29 + (($encoded >> (($month - 1) << 1)) & 3);
}

/** Normalize a value to a 'Y-m-d' AD string (or the datetime/Y-m-d part). */
function bs_normalize_ad($value): ?string
{
    if ($value === null || $value === '') return null;
    if (is_numeric($value)) {
        return date('Y-m-d', (int) $value);
    }
    $s = trim((string) $value);
    if ($s === '' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') return null;
    $datePart = (strlen($s) >= 10) ? substr($s, 0, 10) : $s;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart)) return null;
    [$y, $m, $d] = array_map('intval', explode('-', $datePart));
    if (!checkdate($m, $d, $y)) return null;
    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}

/**
 * Convert an AD date ('Y-m-d' or datetime string / timestamp) to BS parts.
 * Returns ['year'=>, 'month'=>, 'day'=>] or null when invalid/out of range.
 */
function adToBs($adDate): ?array
{
    $ad = bs_normalize_ad($adDate);
    if (!$ad) return null;
    $epoch = new DateTimeImmutable(BS_EPOCH_AD, new DateTimeZone('UTC'));
    $target = new DateTimeImmutable($ad . ' 00:00:00', new DateTimeZone('UTC'));
    $days = (int) $epoch->diff($target)->format('%r%a'); // days after epoch
    if ($days < 0) return null;

    $year = BS_YEAR_ZERO;
    while (true) {
        for ($m = 1; $m <= 12; $m++) {
            $len = bs_days_in_month($year, $m);
            if ($days < $len) {
                return ['year' => $year, 'month' => $m, 'day' => $days + 1];
            }
            $days -= $len;
        }
        $year++;
        if ($year > BS_YEAR_ZERO + count(BS_ENCODED_MONTH_LENGTHS) - 1) return null;
    }
}

/**
 * Convert AD to BS as 'YYYY/MM/DD' (BS calendar is the user-facing one).
 */
function formatBsDate($adDate, bool $includeMonthName = false): string
{
    $bs = adToBs($adDate);
    if (!$bs) return '—';
    if ($includeMonthName) {
        return sprintf('%s %s %04d', $bs['day'], bs_month_names()[$bs['month']], $bs['year']);
    }
    return sprintf('%04d/%02d/%02d', $bs['year'], $bs['month'], $bs['day']);
}

/**
 * Convert AD datetime to BS 'YYYY/MM/DD hh:mm AM/PM'.
 */
function formatBsDateTime($adDateTime): string
{
    $ad = bs_normalize_ad($adDateTime);
    if (!$ad) return '—';
    $timePart = '';
    if (is_numeric($adDateTime)) {
        $timePart = date('h:i A', (int) $adDateTime);
    } elseif (is_string($adDateTime) && preg_match('/\d\d:\d\d/', $adDateTime)) {
        $t = substr((string) $adDateTime, 11); // 'HH:MM:SS' or 'HH:MM'
        $timePart = date('h:i A', strtotime($t));
    }
    $date = formatBsDate($ad);
    return $timePart !== '' ? $date . ' ' . $timePart : $date;
}

/**
 * Convert a BS date string to its AD 'Y-m-d' equivalent.
 * Accepts 'YYYY/MM/DD', 'YYYY-MM-DD', 'YYYY MM DD', arrays not allowed.
 * Returns null if invalid / out of range.
 */
function bsToAd(string $bsDate): ?string
{
    $s = trim($bsDate);
    if (!preg_match('/^\s*(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\s*$/', $s, $m)) return null;
    $y = (int) $m[1]; $mo = (int) $m[2]; $d = (int) $m[3];
    if ($mo < 1 || $mo > 12) return null;
    $idx = $y - BS_YEAR_ZERO;
    if ($idx < 0 || !isset(BS_ENCODED_MONTH_LENGTHS[$idx])) return null;
    $dim = bs_days_in_month($y, $mo);
    if ($d < 1 || $d > $dim) return null;

    $days = $d - 1;
    for ($yy = BS_YEAR_ZERO; $yy < $y; $yy++) {
        for ($mm = 1; $mm <= 12; $mm++) {
            $days += bs_days_in_month($yy, $mm);
        }
    }
    for ($mm = 1; $mm < $mo; $mm++) {
        $days += bs_days_in_month($y, $mm);
    }
    $epoch = new DateTimeImmutable(BS_EPOCH_AD, new DateTimeZone('UTC'));
    return $epoch->modify("+{$days} days")->format('Y-m-d');
}

/** Strict BS date validation. Returns bool. */
function validateBsDate(string $bsDate): bool
{
    return bsToAd($bsDate) !== null;
}

/** Today's AD date ('Y-m-d') in Nepal time. */
function todayAd(?string $format = 'Y-m-d'): string
{
    $tz = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Kathmandu';
    return (new DateTimeImmutable('now', new DateTimeZone($tz)))->format($format);
}

/** Today's BS date as ['year','month','day']. */
function currentBsDate(): ?array
{
    return adToBs(todayAd('Y-m-d'));
}

/** Today's BS date as 'YYYY/MM/DD'. */
function currentBsDateString(): string
{
    $bs = currentBsDate();
    if (!$bs) return '—';
    return sprintf('%04d/%02d/%02d', $bs['year'], $bs['month'], $bs['day']);
}

/** Current BS year (e.g. 2082). */
function currentBsYear(): int
{
    $bs = currentBsDate();
    return $bs ? $bs['year'] : (BS_YEAR_ZERO + 112);
}

/** BS academic-year label e.g. 2082/83 derived from the current BS year. */
function currentBsAcademicYear(): string
{
    $y = currentBsYear();
    return $y . '/' . substr((string) ($y + 1), -2);
}

/** Map any AD value to a BS display string (date -> date, datetime -> datetime). */
function bsDisplay($value): string
{
    if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') return '—';
    if (is_numeric($value)) return formatBsDateTime($value);
    $s = trim((string) $value);
    if (strlen($s) > 10) return formatBsDateTime($s);
    return formatBsDate($s);
}

/** Alias used by templates: convert AD to BS date display. */
function bs_date($adDate): string
{
    return formatBsDate($adDate);
}

/** Alias used by templates: convert AD datetime to BS date+time display. */
function bs_datetime($adDateTime): string
{
    return formatBsDateTime($adDateTime);
}

/**
 * Parse a user-supplied date string entered as BS ("YYYY/MM/DD" or with - . separators)
 * and return the AD "Y-m-d" string. Returns '' for empty/null input.
 * Returns false if the value is not a valid BS date.
 */
function bs_parse_input($value)
{
    if ($value === null || trim((string) $value) === '') return '';
    $ad = bsToAd(trim((string) $value));
    if ($ad === null) return false;
    return $ad;
}

/** Convert an AD "Y-m-d" value to a BS input-string ("YYYY/MM/DD") for form repopulation. */
function bs_input_value($adValue): string
{
    if ($adValue === null || $adValue === '' || $adValue === '0000-00-00' || $adValue === '0000-00-00 00:00:00') return '';
    return formatBsDate($adValue) === '—' ? '' : formatBsDate($adValue);
}