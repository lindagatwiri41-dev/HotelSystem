/**
 * Sunrise Hotel – Booking System
 * script.js
 *
 * Functions implemented:
 *   1. validateBooking()      – validates all form fields
 *   2. calculateBill()        – calculates the estimated total
 *   3. checkRoomCapacity()    – warns if guests exceed room capacity
 *   4. showRoomCapacityInfo() – displays max guests for selected room
 *   5. toggleTransferDetails()– shows/hides transfer info box
 *   6. updateSummary()        – live booking summary panel
 *
 * Events used: submit, change, input, click
 */

// ── Pricing constants ──────────────────────────────────────────────────────
const ROOM_RATES = {
  single: 3500,
  double: 5000,
  family: 7500
};

const ROOM_CAPACITY = {
  single: 1,
  double: 2,
  family: 4
};

const ROOM_LABELS = {
  single: "Single Room",
  double: "Double Room",
  family: "Family Room"
};

const BREAKFAST_RATE      = 700;   // per person per day
const TRANSFER_COST       = 2000;
const EXTRA_BED_RATE      = 1500;  // per night (individualization: last digit 7)

// ── DOM references ─────────────────────────────────────────────────────────
const form             = document.getElementById("bookingForm");
const fullNameInput    = document.getElementById("fullName");
const idNumberInput    = document.getElementById("idNumber");
const emailInput       = document.getElementById("email");
const phoneInput       = document.getElementById("phone");
const checkinDateInput = document.getElementById("checkinDate");
const numNightsInput   = document.getElementById("numNights");
const roomTypeSelect   = document.getElementById("roomType");
const numGuestsInput   = document.getElementById("numGuests");
const breakfastRadios  = document.querySelectorAll('input[name="breakfast"]');
const transferRadios   = document.querySelectorAll('input[name="airportTransfer"]');
const extraBedChk      = document.getElementById("extraBed");

const roomCapacityInfo  = document.getElementById("roomCapacityInfo");
const capacityWarning   = document.getElementById("capacityWarning");
const transferDetails   = document.getElementById("transferDetails");
const summaryContent    = document.getElementById("summaryContent");
const billBreakdown     = document.getElementById("billBreakdown");
const billRows          = document.getElementById("billRows");
const billTotal         = document.getElementById("billTotal");
const jsAmountField     = document.getElementById("jsCalculatedAmount");

// ── Utility: show/clear field error ───────────────────────────────────────
function showError(inputEl, errorId, message) {
  const errEl = document.getElementById(errorId);
  if (errEl) errEl.textContent = message;
  if (inputEl) inputEl.classList.add("input-error");
}

function clearError(inputEl, errorId) {
  const errEl = document.getElementById(errorId);
  if (errEl) errEl.textContent = "";
  if (inputEl) inputEl.classList.remove("input-error");
}

// ── 1. validateBooking() ──────────────────────────────────────────────────
/**
 * Validates all required form fields.
 * Returns true if all fields pass, false otherwise.
 */
function validateBooking() {
  let isValid = true;

  // 1a. Full name must not be empty
  if (fullNameInput.value.trim() === "") {
    showError(fullNameInput, "fullNameError", "Full name is required.");
    isValid = false;
  } else {
    clearError(fullNameInput, "fullNameError");
  }

  // 1b. National ID / Passport must not be empty
  if (idNumberInput.value.trim() === "") {
    showError(idNumberInput, "idNumberError", "ID or passport number is required.");
    isValid = false;
  } else {
    clearError(idNumberInput, "idNumberError");
  }

  // 1c. Email must have valid format
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(emailInput.value.trim())) {
    showError(emailInput, "emailError", "Please enter a valid email address.");
    isValid = false;
  } else {
    clearError(emailInput, "emailError");
  }

  // 1d. Phone must contain exactly 10 digits
  const phoneDigits = phoneInput.value.replace(/\D/g, "");
  if (phoneDigits.length !== 10) {
    showError(phoneInput, "phoneError", "Phone number must contain exactly 10 digits.");
    isValid = false;
  } else {
    clearError(phoneInput, "phoneError");
  }

  // 1e. Check-in date must be selected
  if (!checkinDateInput.value) {
    showError(checkinDateInput, "checkinDateError", "Please select a check-in date.");
    isValid = false;
  } else {
    clearError(checkinDateInput, "checkinDateError");
  }

  // 1f. Number of nights must be 1–14
  const nights = parseInt(numNightsInput.value, 10);
  if (isNaN(nights) || nights < 1 || nights > 14) {
    showError(numNightsInput, "numNightsError", "Number of nights must be between 1 and 14.");
    isValid = false;
  } else {
    clearError(numNightsInput, "numNightsError");
  }

  // 1g. Room type must be selected
  if (roomTypeSelect.value === "") {
    showError(roomTypeSelect, "roomTypeError", "Please select a room type.");
    isValid = false;
  } else {
    clearError(roomTypeSelect, "roomTypeError");
  }

  // 1h. Number of guests must be > 0
  const guests = parseInt(numGuestsInput.value, 10);
  if (isNaN(guests) || guests < 1) {
    showError(numGuestsInput, "numGuestsError", "Number of guests must be at least 1.");
    isValid = false;
  } else {
    clearError(numGuestsInput, "numGuestsError");
  }

  return isValid;
}

// ── 2. calculateBill() ────────────────────────────────────────────────────
/**
 * Reads current form values, computes total cost, updates the bill panel,
 * and stores the total in the hidden field for PHP verification.
 */
function calculateBill() {
  const roomType = roomTypeSelect.value;
  const nights   = parseInt(numNightsInput.value, 10)  || 0;
  const guests   = parseInt(numGuestsInput.value, 10)  || 0;

  const breakfastVal  = document.querySelector('input[name="breakfast"]:checked')?.value   || "no";
  const transferVal   = document.querySelector('input[name="airportTransfer"]:checked')?.value || "no";
  const extraBed      = extraBedChk ? extraBedChk.checked : false;

  // Calculate each line item
  const roomRate      = ROOM_RATES[roomType] || 0;
  const roomCost      = roomRate * nights;
  const breakfastCost = (breakfastVal === "yes") ? (BREAKFAST_RATE * guests * nights) : 0;
  const transferCost  = (transferVal  === "yes") ? TRANSFER_COST : 0;
  const extraBedCost  = extraBed ? (EXTRA_BED_RATE * nights) : 0;

  const total = roomCost + breakfastCost + transferCost + extraBedCost;

  // Store in hidden field so PHP can compare
  if (jsAmountField) jsAmountField.value = total;

  // Build bill rows
  if (billRows && billTotal && billBreakdown) {
    let rowsHTML = "";

    if (roomCost > 0) {
      rowsHTML += `<tr>
        <td>${ROOM_LABELS[roomType]} (${nights} night${nights !== 1 ? "s" : ""} × KSh ${roomRate.toLocaleString()})</td>
        <td>KSh ${roomCost.toLocaleString()}</td>
      </tr>`;
    }

    if (breakfastCost > 0) {
      rowsHTML += `<tr>
        <td>Breakfast (${guests} guest${guests !== 1 ? "s" : ""} × ${nights} night${nights !== 1 ? "s" : ""} × KSh ${BREAKFAST_RATE})</td>
        <td>KSh ${breakfastCost.toLocaleString()}</td>
      </tr>`;
    }

    if (transferCost > 0) {
      rowsHTML += `<tr>
        <td>Airport Transfer</td>
        <td>KSh ${transferCost.toLocaleString()}</td>
      </tr>`;
    }

    if (extraBedCost > 0) {
      rowsHTML += `<tr>
        <td>Extra Bed (${nights} night${nights !== 1 ? "s" : ""} × KSh ${EXTRA_BED_RATE.toLocaleString()})</td>
        <td>KSh ${extraBedCost.toLocaleString()}</td>
      </tr>`;
    }

    billRows.innerHTML = rowsHTML;
    billTotal.innerHTML = `<strong>KSh ${total.toLocaleString()}</strong>`;

    if (total > 0) {
      billBreakdown.style.display = "block";
    } else {
      billBreakdown.style.display = "none";
    }
  }

  return total;
}

// ── 3. checkRoomCapacity() ────────────────────────────────────────────────
/**
 * Compares the entered number of guests against the selected room's
 * maximum capacity and shows/hides the warning box accordingly.
 */
function checkRoomCapacity() {
  const roomType = roomTypeSelect.value;
  const guests   = parseInt(numGuestsInput.value, 10);
  const maxCap   = ROOM_CAPACITY[roomType];

  if (capacityWarning) {
    if (roomType && !isNaN(guests) && guests > maxCap) {
      capacityWarning.style.display = "block";
      capacityWarning.textContent =
        `${ROOM_LABELS[roomType]} has a maximum capacity of ${maxCap} guest${maxCap !== 1 ? "s" : ""}. Please reduce the number of guests or choose a larger room.`;
    } else {
      capacityWarning.style.display = "none";
    }
  }
}

// ── 4. showRoomCapacityInfo() ─────────────────────────────────────────────
/**
 * Displays the max-guest info box whenever a room type is selected.
 * Dynamic feature #1 (no page reload).
 */
function showRoomCapacityInfo() {
  const roomType = roomTypeSelect.value;

  if (roomCapacityInfo) {
    if (roomType) {
      const cap = ROOM_CAPACITY[roomType];
      const rate = ROOM_RATES[roomType];
      roomCapacityInfo.style.display = "block";
      roomCapacityInfo.innerHTML =
        `<strong>${ROOM_LABELS[roomType]}</strong> — Maximum capacity: <strong>${cap} guest${cap !== 1 ? "s" : ""}</strong> &nbsp;|&nbsp; Rate: <strong>KSh ${rate.toLocaleString()}/night</strong>`;
    } else {
      roomCapacityInfo.style.display = "none";
    }
  }
}

// ── 5. toggleTransferDetails() ────────────────────────────────────────────
/**
 * Shows the airport transfer info box only when the user selects "Yes".
 * Dynamic feature #2 (no page reload).
 */
function toggleTransferDetails() {
  const transferYes = document.getElementById("transferYes");
  if (transferDetails) {
    transferDetails.style.display = (transferYes && transferYes.checked) ? "block" : "none";
  }
}

// ── 6. updateSummary() ────────────────────────────────────────────────────
/**
 * Builds a text summary of the current booking selection in real time.
 * Dynamic feature #3 (no page reload).
 */
function updateSummary() {
  const roomType    = roomTypeSelect.value;
  const nights      = parseInt(numNightsInput.value, 10)  || 0;
  const guests      = parseInt(numGuestsInput.value, 10)  || 0;
  const checkinVal  = checkinDateInput.value;
  const breakfastSelected = document.querySelector('input[name="breakfast"]:checked')?.value === "yes";
  const transferSelected  = document.querySelector('input[name="airportTransfer"]:checked')?.value === "yes";
  const extraBedSelected  = extraBedChk ? extraBedChk.checked : false;

  if (!roomType || nights < 1) {
    summaryContent.innerHTML = '<p class="summary-placeholder">Select your options to see a live summary.</p>';
    return;
  }

  // Format check-in date nicely
  let checkinDisplay = "—";
  if (checkinVal) {
    const d = new Date(checkinVal + "T00:00:00");
    checkinDisplay = d.toLocaleDateString("en-KE", { day: "numeric", month: "long", year: "numeric" });
  }

  let html = `
    <p><strong>Room:</strong> ${ROOM_LABELS[roomType]}</p>
    <p><strong>Check-in:</strong> ${checkinDisplay}</p>
    <p><strong>Nights:</strong> ${nights}</p>
    <p><strong>Guests:</strong> ${guests}</p>
    <p><strong>Breakfast:</strong> ${breakfastSelected ? "Yes" : "No"}</p>
    <p><strong>Airport Transfer:</strong> ${transferSelected ? "Yes" : "No"}</p>
    <p><strong>Extra Bed:</strong> ${extraBedSelected ? "Yes" : "No"}</p>
  `;

  summaryContent.innerHTML = html;
}

// ── Wiring up events ───────────────────────────────────────────────────────

if (form) {

  // submit event – validate before sending to PHP
  form.addEventListener("submit", function (e) {
    const valid = validateBooking();
    if (!valid) {
      e.preventDefault(); // block submission
    } else {
      calculateBill();    // ensure hidden field is up to date
    }
  });

  // change events on room type and guests – capacity check + bill recalc
  roomTypeSelect.addEventListener("change", function () {
    showRoomCapacityInfo();
    checkRoomCapacity();
    calculateBill();
    updateSummary();
  });

  numGuestsInput.addEventListener("input", function () {
    checkRoomCapacity();
    calculateBill();
    updateSummary();
  });

  // input events on nights – recalc bill + summary
  numNightsInput.addEventListener("input", function () {
    calculateBill();
    updateSummary();
  });

  // change events on radio buttons and checkbox
  breakfastRadios.forEach(function (radio) {
    radio.addEventListener("change", function () {
      calculateBill();
      updateSummary();
    });
  });

  transferRadios.forEach(function (radio) {
    radio.addEventListener("change", function () {
      toggleTransferDetails();
      calculateBill();
      updateSummary();
    });
  });

  if (extraBedChk) {
    extraBedChk.addEventListener("change", function () {
      calculateBill();
      updateSummary();
    });
  }

  // check-in date change
  checkinDateInput.addEventListener("change", function () {
    updateSummary();
  });

  // Live inline validation on blur for key fields
  fullNameInput.addEventListener("blur", function () {
    if (fullNameInput.value.trim() === "") {
      showError(fullNameInput, "fullNameError", "Full name is required.");
    } else {
      clearError(fullNameInput, "fullNameError");
    }
  });

  emailInput.addEventListener("blur", function () {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(emailInput.value.trim())) {
      showError(emailInput, "emailError", "Please enter a valid email address.");
    } else {
      clearError(emailInput, "emailError");
    }
  });

  phoneInput.addEventListener("blur", function () {
    const digits = phoneInput.value.replace(/\D/g, "");
    if (digits.length !== 10) {
      showError(phoneInput, "phoneError", "Phone number must contain exactly 10 digits.");
    } else {
      clearError(phoneInput, "phoneError");
    }
  });

  numNightsInput.addEventListener("blur", function () {
    const v = parseInt(numNightsInput.value, 10);
    if (isNaN(v) || v < 1 || v > 14) {
      showError(numNightsInput, "numNightsError", "Number of nights must be between 1 and 14.");
    } else {
      clearError(numNightsInput, "numNightsError");
    }
  });

  // Set minimum check-in date to today
  const today = new Date().toISOString().split("T")[0];
  if (checkinDateInput) checkinDateInput.setAttribute("min", today);
}
