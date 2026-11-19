document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector('.appointment-form');
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('email');
    const contactNumberInput = document.getElementById('contactNumber');

    if (form && fullNameInput && emailInput && contactNumberInput) {
        
        // 1. Name Field: Only allow letters and spaces
        fullNameInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^A-Za-z\s]/g, '');
        });

        // 2. Email Field: Only allow letters, numbers, @, _, and .
        emailInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^A-Za-z0-9@_.]/g, '');
        });

        // 3. Contact Number Field: Only allow numbers
        contactNumberInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // 4. Form Submission Validation
        form.addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessage = "";

            // Check if name is valid (not just empty spaces)
            if (fullNameInput.value.trim() === "") {
                isValid = false;
                errorMessage += "Please enter a valid full name.\n";
            }

            // Check if email format is strictly valid (e.g., name@domain.com)
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailInput.value)) {
                isValid = false;
                errorMessage += "Please enter a valid email address.\n";
            }

            // Check if contact number has a valid minimum length (assuming PH mobile format)
            if (contactNumberInput.value.length < 7) {
                isValid = false;
                errorMessage += "Please enter a valid contact number.\n";
            }

            // Prevent form submission if any validation fails
            if (!isValid) {
                e.preventDefault(); 
                alert(errorMessage);
            }
        });
    }

    // Specific behaviors or placeholder polyfills for date inputs on mobile devices
    const dateInput = document.getElementById('appointmentDate');
    if (dateInput) {
        // Fallback: Get today's date and set min attribute via JS as well
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);

        // Ensures smooth validation cues if needed
        dateInput.addEventListener('focus', () => {
            dateInput.type = 'date';
        });
    }
});