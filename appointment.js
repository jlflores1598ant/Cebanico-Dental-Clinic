document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector('.appointment-form');
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('email');
    const contactNumberInput = document.getElementById('contactNumber');
    const serviceSelect = document.getElementById('service');
    const dateInput = document.getElementById('appointmentDate');
    const consentCheckbox = document.getElementById('consent');

    // Pop-up Elements
    const popup = document.getElementById('restrictionPopup');
    const popupTitle = document.getElementById('restrictionPopupTitle');
    const popupMsg = document.getElementById('restrictionPopupMsg');
    const closePopupBtn = document.getElementById('closeRestrictionPopup');
    let popupTimeout;

    function showRestrictionPopup(message, targetEl = null, title = "Input Restriction") {
        if (!popup) return;
        if (!popupMsg) return;
        if (!popupTitle) return;

        popupTitle.textContent = title;
        popupMsg.textContent = message;
        popup.classList.add('show');

        if (targetEl) {
            targetEl.classList.remove('input-error');
            void targetEl.offsetWidth;
            targetEl.classList.add('input-error');
            setTimeout(() => targetEl.classList.remove('input-error'), 1500);
        }

        clearTimeout(popupTimeout);
        popupTimeout = setTimeout(() => {
            popup.classList.remove('show');
        }, 3800);
    }

    if (closePopupBtn && popup) {
        closePopupBtn.addEventListener('click', () => {
            popup.classList.remove('show');
            clearTimeout(popupTimeout);
        });
    }

    // Dynamic Date Bounds: Today through December 31 of Next Year
    const now = new Date();
    const currentYear = now.getFullYear();
    const maxAllowedYear = currentYear + 1;
    const localMonth = String(now.getMonth() + 1).padStart(2, '0');
    const localDay = String(now.getDate()).padStart(2, '0');
    const todayStr = `${currentYear}-${localMonth}-${localDay}`;
    const maxDateStr = `${maxAllowedYear}-12-31`;

    if (form && fullNameInput && emailInput && contactNumberInput) {

        // 1. Full Name Field: Only allow letters and spaces
        fullNameInput.addEventListener('input', function () {
            const raw = this.value;
            if (/[0-9]/.test(raw)) {
                showRestrictionPopup(
                    "You cannot type numbers in this field. Please input your name.",
                    this,
                    "Full Name Restriction"
                );
            } else if (/[^A-Za-z\s]/.test(raw)) {
                showRestrictionPopup(
                    "Special characters are not allowed in this field. Please input your name using letters only.",
                    this,
                    "Full Name Restriction"
                );
            }
            this.value = raw.replace(/[^A-Za-z\s]/g, '');
        });

        // 2. Email Field: Only allow letters, numbers, @, _, ., and -
        emailInput.addEventListener('input', function () {
            const raw = this.value;
            if (/\s/.test(raw)) {
                showRestrictionPopup(
                    "Spaces are not allowed in the email field. Please input a valid email address.",
                    this,
                    "Email Restriction"
                );
            } else if (/[^A-Za-z0-9@_.-]/.test(raw)) {
                showRestrictionPopup(
                    "Special characters like that are not allowed in the email field.",
                    this,
                    "Email Restriction"
                );
            }
            this.value = raw.replace(/[^A-Za-z0-9@_.-]/g, '');
        });

        // 3. Contact Number Field: Must start with 09, numbers only, 11 digits max
        contactNumberInput.addEventListener('input', function () {
            const raw = this.value;

            if (/[A-Za-z]/.test(raw)) {
                showRestrictionPopup(
                    "You cannot type letters in the contact number field. Please enter numbers only.",
                    this,
                    "Contact Number Restriction"
                );
            } else if (/[^0-9]/.test(raw)) {
                showRestrictionPopup(
                    "Special characters and spaces are not allowed. Please enter numbers only.",
                    this,
                    "Contact Number Restriction"
                );
            }

            let digitsOnly = raw.replace(/[^0-9]/g, '');

            // Convert pasted +639XXXXXXXXX into 09XXXXXXXXX automatically
            if (digitsOnly.length === 12 && digitsOnly.startsWith('639')) {
                digitsOnly = '0' + digitsOnly.slice(2);
            }

            // Block any starting digits other than 09
            if (digitsOnly.length >= 1 && digitsOnly.charAt(0) !== '0') {
                showRestrictionPopup(
                    "Philippine mobile numbers must start with 09 (e.g., 09171234567).",
                    this,
                    "Contact Number Prefix Restriction"
                );
                digitsOnly = '';
            } else if (digitsOnly.length >= 2 && digitsOnly.substring(0, 2) !== '09') {
                showRestrictionPopup(
                    "Philippine mobile numbers must start with 09 (e.g., 09171234567).",
                    this,
                    "Contact Number Prefix Restriction"
                );
                digitsOnly = '0';
            }

            // Enforce 11-digit limit
            if (digitsOnly.length > 11) {
                showRestrictionPopup(
                    "Philippine mobile numbers must be 11 digits (e.g., 09171234567).",
                    this,
                    "Contact Number Limit"
                );
                digitsOnly = digitsOnly.slice(0, 11);
            }

            this.value = digitsOnly;
        });

        // 4. Date Field: Today up to Dec 31 of Next Year, excluding Thursdays
        if (dateInput) {
            dateInput.setAttribute('min', todayStr);
            dateInput.setAttribute('max', maxDateStr);

            dateInput.addEventListener('focus', () => {
                dateInput.type = 'date';
            });

            dateInput.addEventListener('change', function () {
                if (!this.value) return;

                if (this.value < todayStr) {
                    showRestrictionPopup(
                        "You cannot select a past date. Please choose today or an upcoming date.",
                        this,
                        "Invalid Date"
                    );
                    this.value = '';
                    return;
                }

                if (this.value > maxDateStr) {
                    showRestrictionPopup(
                        `Appointments can only be booked up to December 31, ${maxAllowedYear}. Please choose an earlier date.`,
                        this,
                        "Date Limit Exceeded"
                    );
                    this.value = '';
                    return;
                }

                const [year, month, day] = this.value.split('-').map(Number);
                const selectedDate = new Date(year, month - 1, day);
                if (selectedDate.getDay() === 4) {
                    showRestrictionPopup(
                        "Cebanico Dental Clinic is closed on Thursdays. Please choose another day.",
                        this,
                        "Clinic Closed on Thursdays"
                    );
                    this.value = '';
                }
            });
        }

        // 5. Form Submission Validation
        form.addEventListener('submit', function (e) {
            if (fullNameInput.value.trim().length < 2) {
                e.preventDefault();
                fullNameInput.focus();
                showRestrictionPopup(
                    "Please input your full name (at least 2 letters).",
                    fullNameInput,
                    "Full Name Required"
                );
                return;
            }

            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailInput.value.trim())) {
                e.preventDefault();
                emailInput.focus();
                showRestrictionPopup(
                    "Please enter a valid email address (e.g., name@example.com).",
                    emailInput,
                    "Invalid Email Address"
                );
                return;
            }

            if (!/^09[0-9]{9}$/.test(contactNumberInput.value)) {
                e.preventDefault();
                contactNumberInput.focus();
                showRestrictionPopup(
                    "Please enter a valid 11-digit Philippine mobile number starting with 09 (e.g., 09171234567).",
                    contactNumberInput,
                    "Invalid Contact Number"
                );
                return;
            }

            if (!serviceSelect) {
                e.preventDefault();
                return;
            }
            if (serviceSelect.value === "") {
                e.preventDefault();
                serviceSelect.focus();
                showRestrictionPopup(
                    "Please select the type of dental service you want to book.",
                    serviceSelect,
                    "Service Required"
                );
                return;
            }

            if (!dateInput) {
                e.preventDefault();
                return;
            }
            if (dateInput.value === "") {
                e.preventDefault();
                dateInput.focus();
                showRestrictionPopup(
                    "Please select your preferred day of appointment.",
                    dateInput,
                    "Appointment Date Required"
                );
                return;
            }
            if (dateInput.value < todayStr) {
                e.preventDefault();
                dateInput.focus();
                showRestrictionPopup(
                    "Appointment date cannot be in the past.",
                    dateInput,
                    "Invalid Date"
                );
                return;
            }
            if (dateInput.value > maxDateStr) {
                e.preventDefault();
                dateInput.focus();
                showRestrictionPopup(
                    `Appointments can only be booked up to December 31, ${maxAllowedYear}.`,
                    dateInput,
                    "Date Limit Exceeded"
                );
                return;
            }

            if (!consentCheckbox) {
                e.preventDefault();
                return;
            }
            if (!consentCheckbox.checked) {
                e.preventDefault();
                consentCheckbox.focus();
                showRestrictionPopup(
                    "Please check the consent box so we can contact you about this appointment.",
                    consentCheckbox,
                    "Consent Required"
                );
                return;
            }
        });
    }
});