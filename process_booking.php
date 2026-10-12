<?php
/**
 * Sunrise Hotel – Booking Processor
 * process_booking.php
 *
 * Retrieves POST data, performs server-side validation,
 * independently calculates the booking total (does NOT trust JS amount),
 * and displays a formatted booking confirmation.
 *
 * Individualization: last digit = 7 → Extra Bed option @ KSh 1,500/night
 */

// ── Pricing constants (must mirror script.js values) ──────────────────────
define('RATE_SINGLE',        3500);
define('RATE_DOUBLE',        5000);
define('RATE_FAMILY',        7500);
define('RATE_BREAKFAST',      700);   // per person per night
define('RATE_TRANSFER',      2000);
define('RATE_EXTRA_BED',     1500);   // per night

// ── Helper: sanitize input ─────────────────────────────────────────────────
function clean(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// ── Collect POST data ──────────────────────────────────────────────────────
$fullName       = clean($_POST['fullName']       ?? '');
$idNumber       = clean($_POST['idNumber']       ?? '');
$email          = clean($_POST['email']          ?? '');
$phone          = clean($_POST['phone']          ?? '');
$checkinDate    = clean($_POST['checkinDate']    ?? '');
$numNightsRaw   = $_POST['numNights']            ?? '';
$roomType       = clean($_POST['roomType']       ?? '');
$numGuestsRaw   = $_POST['numGuests']            ?? '';
$breakfast      = clean($_POST['breakfast']      ?? 'no');
$airportTransfer= clean($_POST['airportTransfer']?? 'no');
$extraBed       = clean($_POST['extraBed']       ?? 'no');   // checkbox

// ── Server-side validation ─────────────────────────────────────────────────
$errors = [];

// 1. Full name
if (empty($fullName)) {
    $errors[] = "Full name is required.";
}

// 2. National ID / Passport
if (empty($idNumber)) {
    $errors[] = "National ID or Passport number is required.";
}

// 3. Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "A valid email address is required.";
}

// 4. Phone – exactly 10 digits
$phoneDigits = preg_replace('/\D/', '', $phone);
if (strlen($phoneDigits) !== 10) {
    $errors[] = "Phone number must contain exactly 10 digits.";
}

// 5. Check-in date
if (empty($checkinDate) || !strtotime($checkinDate)) {
    $errors[] = "A valid check-in date is required.";
}

// 6. Number of nights (1–14)
$numNights = filter_var($numNightsRaw, FILTER_VALIDATE_INT);
if ($numNights === false || $numNights < 1 || $numNights > 14) {
    $errors[] = "Number of nights must be between 1 and 14.";
    $numNights = 0;
}

// 7. Room type
$validRooms = ['single', 'double', 'family'];
if (!in_array($roomType, $validRooms)) {
    $errors[] = "A valid room type must be selected (Single, Double, or Family).";
}

// 8. Number of guests
$numGuests = filter_var($numGuestsRaw, FILTER_VALIDATE_INT);
if ($numGuests === false || $numGuests < 1) {
    $errors[] = "Number of guests must be at least 1.";
    $numGuests = 0;
}

// 9. Guest capacity check (server-side)
$capacityMap = ['single' => 1, 'double' => 2, 'family' => 4];
if (!empty($roomType) && $numGuests > 0) {
    $maxCap = $capacityMap[$roomType] ?? 0;
    if ($numGuests > $maxCap) {
        $errors[] = "Number of guests ({$numGuests}) exceeds the capacity of the selected room ({$maxCap}).";
    }
}

// ── If there are errors, show error page ──────────────────────────────────
if (!empty($errors)) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Booking Error – Sunrise Hotel</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
  <nav class="navbar">
    <div class="nav-brand">
      <img src="Sunrise.png" alt="Sunrise Hotel Logo" class="nav-logo" />
    </div>
    <ul class="nav-links">
      <li><a href="index.html">Home</a></li>
      <li><a href="booking.html">Book a Room</a></li>
    </ul>
  </nav>

  <div class="error-page">
    <h2>
      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d32f2f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:8px;">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      Booking Could Not Be Processed
    </h2>
    <p>Please fix the following issues and try again:</p>
    <ul class="error-list">
      <?php foreach ($errors as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
      <?php endforeach; ?>
    </ul>
    <a href="booking.html">← Go Back to Booking Form</a>
  </div>

  <footer class="footer">
    <p>&copy; 2026 Sunrise Hotel. All rights reserved.</p>
  </footer>
</body>
</html>
<?php
    exit;
}

// ── Independent bill calculation (PHP does NOT trust JS amount) ───────────
$rateMap = [
    'single' => RATE_SINGLE,
    'double' => RATE_DOUBLE,
    'family' => RATE_FAMILY,
];
$roomLabelMap = [
    'single' => 'Single Room',
    'double' => 'Double Room',
    'family' => 'Family Room',
];

$roomRate      = $rateMap[$roomType];
$roomCost      = $roomRate * $numNights;
$breakfastCost = ($breakfast === 'yes') ? (RATE_BREAKFAST * $numGuests * $numNights) : 0;
$transferCost  = ($airportTransfer === 'yes') ? RATE_TRANSFER : 0;
$extraBedCost  = ($extraBed === 'yes') ? (RATE_EXTRA_BED * $numNights) : 0;

$totalAmount = $roomCost + $breakfastCost + $transferCost + $extraBedCost;

// Format the check-in date nicely
$checkinFormatted = date('d F Y', strtotime($checkinDate));

// Calculate check-out date
$checkoutDate = date('d F Y', strtotime($checkinDate . " + {$numNights} days"));

// Generate a simple booking reference
$bookingRef = 'SH-' . strtoupper(substr(md5(uniqid()), 0, 8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Booking Confirmed – Sunrise Hotel</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>

  <!-- NAVIGATION -->
  <nav class="navbar">
    <div class="nav-brand">
      <img src="Sunrise.png" alt="Sunrise Hotel Logo" class="nav-logo" />
    </div>
    <ul class="nav-links">
      <li><a href="index.html">Home</a></li>
      <li><a href="booking.html">Book a Room</a></li>
    </ul>
  </nav>

  <div class="confirmation-wrapper">
    <div class="confirmation-card">

      <!-- HEADER -->
      <div class="confirmation-header">
        <span class="check-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" fill="rgba(255,255,255,0.15)" stroke="#ffffff"/>
            <polyline points="5 12 10 17 19 7" stroke="#ffffff"/>
          </svg>
        </span>
        <h1>BOOKING CONFIRMED</h1>
        <p>Booking Reference: <strong><?= $bookingRef ?></strong></p>
      </div>

      <!-- BODY -->
      <div class="confirmation-body">

        <!-- Customer Details -->
        <div class="detail-group">
          <h3>Customer Details</h3>
          <div class="detail-row">
            <span class="label">Full Name</span>
            <span class="value"><?= $fullName ?></span>
          </div>
          <div class="detail-row">
            <span class="label">ID / Passport</span>
            <span class="value"><?= $idNumber ?></span>
          </div>
          <div class="detail-row">
            <span class="label">Email</span>
            <span class="value"><?= $email ?></span>
          </div>
          <div class="detail-row">
            <span class="label">Phone</span>
            <span class="value"><?= $phone ?></span>
          </div>
        </div>

        <!-- Booking Details -->
        <div class="detail-group">
          <h3>Booking Details</h3>
          <div class="detail-row">
            <span class="label">Room Type</span>
            <span class="value"><?= $roomLabelMap[$roomType] ?></span>
          </div>
          <div class="detail-row">
            <span class="label">Number of Guests</span>
            <span class="value"><?= $numGuests ?></span>
          </div>
          <div class="detail-row">
            <span class="label">Check-in Date</span>
            <span class="value"><?= $checkinFormatted ?></span>
          </div>
          <div class="detail-row">
            <span class="label">Check-out Date</span>
            <span class="value"><?= $checkoutDate ?></span>
          </div>
          <div class="detail-row">
            <span class="label">Number of Nights</span>
            <span class="value"><?= $numNights ?></span>
          </div>
        </div>

        <!-- Services -->
        <div class="detail-group">
          <h3>Additional Services</h3>
          <div class="detail-row">
            <span class="label">Breakfast</span>
            <span class="value"><?= ucfirst($breakfast) ?></span>
          </div>
          <div class="detail-row">
            <span class="label">Airport Transfer</span>
            <span class="value"><?= ucfirst($airportTransfer) ?></span>
          </div>
          <div class="detail-row">
            <span class="label">Extra Bed</span>
            <span class="value"><?= ($extraBed === 'yes') ? 'Yes' : 'No' ?></span>
          </div>
        </div>

        <!-- Bill Breakdown -->
        <div class="detail-group">
          <h3>Bill Breakdown</h3>
          <div class="detail-row">
            <span class="label"><?= $roomLabelMap[$roomType] ?> (<?= $numNights ?> night<?= $numNights !== 1 ? 's' : '' ?> × KSh <?= number_format($roomRate) ?>)</span>
            <span class="value">KSh <?= number_format($roomCost) ?></span>
          </div>
          <?php if ($breakfastCost > 0): ?>
          <div class="detail-row">
            <span class="label">Breakfast (<?= $numGuests ?> guest<?= $numGuests !== 1 ? 's' : '' ?> × <?= $numNights ?> night<?= $numNights !== 1 ? 's' : '' ?> × KSh <?= RATE_BREAKFAST ?>)</span>
            <span class="value">KSh <?= number_format($breakfastCost) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($transferCost > 0): ?>
          <div class="detail-row">
            <span class="label">Airport Transfer</span>
            <span class="value">KSh <?= number_format($transferCost) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($extraBedCost > 0): ?>
          <div class="detail-row">
            <span class="label">Extra Bed (<?= $numNights ?> night<?= $numNights !== 1 ? 's' : '' ?> × KSh <?= number_format(RATE_EXTRA_BED) ?>)</span>
            <span class="value">KSh <?= number_format($extraBedCost) ?></span>
          </div>
          <?php endif; ?>
        </div>

        <!-- Total -->
        <div class="total-highlight">
          <span class="total-label">TOTAL AMOUNT</span>
          <span class="total-amount">KSh <?= number_format($totalAmount) ?></span>
        </div>

        <p class="thank-you-msg">Thank you for choosing Sunrise Hotel. We look forward to welcoming you!</p>

      </div><!-- end confirmation-body -->

      <div class="confirmation-footer">
        <a href="booking.html">Make Another Booking</a>
      </div>

    </div><!-- end confirmation-card -->
  </div><!-- end confirmation-wrapper -->

  <footer class="footer">
    <p>&copy; 2026 Sunrise Hotel. All rights reserved.</p>
    <p>
      <span class="footer-icon">
        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" width="16" height="16" viewBox="0 0 256 256" xml:space="preserve">
          <g transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)">
            <path d="M 45 90 c -1.415 0 -2.725 -0.748 -3.444 -1.966 l -4.385 -7.417 C 28.167 65.396 19.664 51.02 16.759 45.189 c -2.112 -4.331 -3.175 -8.955 -3.175 -13.773 C 13.584 14.093 27.677 0 45 0 c 17.323 0 31.416 14.093 31.416 31.416 c 0 4.815 -1.063 9.438 -3.157 13.741 c -0.025 0.052 -0.053 0.104 -0.08 0.155 c -2.961 5.909 -11.41 20.193 -20.353 35.309 l -4.382 7.413 C 47.725 89.252 46.415 90 45 90 z" style="fill: #c8962a;" />
            <path d="M 45 45.678 c -8.474 0 -15.369 -6.894 -15.369 -15.368 S 36.526 14.941 45 14.941 c 8.474 0 15.368 6.895 15.368 15.369 S 53.474 45.678 45 45.678 z" style="fill: #ffffff;" />
          </g>
        </svg>
      </span>
      Nairobi, Kenya &nbsp;|&nbsp; 
      <span class="footer-icon">
        <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="16" height="16" viewBox="0 0 256 256" xml:space="preserve">
          <g transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)">
            <circle cx="45" cy="45" r="45" style="fill: #c8962a;"/>
            <path d="M 61.305 73.895 l 5.959 -3.006 c 2.834 -1.43 3.174 -5.342 0.629 -7.239 l -8.995 -6.706 c -1.357 -1.011 -3.182 -1.127 -4.655 -0.294 l -2.613 1.477 c -1.253 0.708 -2.788 0.755 -4.059 0.079 c -6.864 -3.654 -12.123 -8.913 -15.777 -15.777 c -0.676 -1.271 -0.63 -2.806 0.079 -4.059 l 1.477 -2.613 c 0.833 -1.473 0.717 -3.298 -0.294 -4.655 l -6.706 -8.995 c -1.897 -2.545 -5.809 -2.205 -7.239 0.629 l -3.006 5.959 c -1.405 2.785 -1.482 6.068 -0.164 8.894 c 7.733 16.58 19.889 28.736 36.469 36.469 C 55.237 75.377 58.52 75.3 61.305 73.895 z" style="fill: rgb(255,255,255);"/>
            <path d="M 72.275 50.419 c -1.505 0 -2.725 -1.22 -2.725 -2.725 c 0 -15.023 -12.222 -27.245 -27.245 -27.245 c -1.505 0 -2.725 -1.22 -2.725 -2.725 c 0 -1.505 1.22 -2.725 2.725 -2.725 C 60.333 15 75 29.667 75 47.694 C 75 49.199 73.78 50.419 72.275 50.419 z" style="fill: rgb(255,255,255);"/>
            <path d="M 61.377 50.419 c -1.505 0 -2.725 -1.22 -2.725 -2.725 c 0 -9.014 -7.333 -16.347 -16.347 -16.347 c -1.505 0 -2.725 -1.22 -2.725 -2.725 c 0 -1.505 1.22 -2.725 2.725 -2.725 c 12.019 0 21.796 9.778 21.796 21.796 C 64.102 49.199 62.882 50.419 61.377 50.419 z" style="fill: rgb(255,255,255);"/>
          </g>
        </svg>
      </span>
      +254 700 000 000 &nbsp;|&nbsp;
      <span class="footer-icon">
        <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="16" height="16" viewBox="0 0 256 256" xml:space="preserve">
          <g transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)">
            <circle cx="45" cy="45" r="45" style="fill: #c8962a;"/>
            <path d="M 54.717 63.299 c -0.264 -0.074 -0.566 -0.011 -0.769 0.164 c -5.643 5.009 -7.288 5.625 -7.734 5.657 c -0.056 0.004 -0.18 -0.048 -0.344 -0.211 c -0.206 -0.201 -0.317 -0.465 -0.342 -0.807 c -0.172 -2.383 1.447 -9.741 4.812 -21.87 c 2.826 -10.143 3.089 -12.2 3.041 -12.863 c -0.071 -0.99 -0.563 -1.759 -1.46 -2.287 c -0.854 -0.501 -2.025 -0.701 -3.477 -0.596 c -2.448 0.177 -5.362 1.206 -8.661 3.06 c -0.943 0.531 -1.926 1.166 -2.92 1.886 c -2.622 1.9 -4.06 4.79 -3.848 7.729 c 0.017 0.241 0.206 0.446 0.478 0.522 c 0.273 0.075 0.578 0.005 0.773 -0.177 c 2.602 -2.419 4.335 -3.902 5.153 -4.409 c 0.873 -0.54 1.651 -0.837 2.315 -0.885 c 0.245 -0.018 0.368 -0.027 0.397 0.38 c 0.039 0.541 -0.047 1.188 -0.255 1.919 c -4.927 16.991 -7.17 27.343 -6.86 31.647 c 0.106 1.463 0.672 2.6 1.684 3.382 c 1.024 0.793 2.363 1.137 3.976 1.02 c 1.757 -0.127 3.866 -0.902 6.446 -2.369 c 1.241 -0.706 2.849 -1.847 4.78 -3.391 c 2.277 -1.822 3.475 -4.366 3.287 -6.98 C 55.171 63.581 54.987 63.377 54.717 63.299 z" style="fill: rgb(255,255,255);"/>
            <circle cx="50.830999999999996" cy="19.591" r="6.171" style="fill: rgb(255,255,255);"/>
          </g>
        </svg>
      </span>
      info@sunrisehotel.co.ke
    </p>
  </footer>

</body>
</html>
