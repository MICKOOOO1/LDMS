document.addEventListener('DOMContentLoaded', () => {

    // ===== Helper function: show inline messages =====
    const setMessage = (input, message, isValid) => {
        const msgEl = input.parentElement.querySelector('.input-message');
        if (msgEl) {
            if (message.includes('<')) {
                msgEl.innerHTML = message;
            } else {
                msgEl.textContent = message;
                msgEl.style.color = isValid ? 'green' : 'red';
            }
        }

        // For username field, also update the user icon color
        if (input.name === 'username') {
            const userIcon = input.parentElement.querySelector('i.bxs-user');
            if (userIcon) {
                userIcon.style.color = isValid ? 'green' : 'red';
            }
        }
    };

    // (server-side now handles relaxed rules; no need for extra helpers here)

    // ===== Helper function: field labels =====
    const fieldLabel = name => {
        switch(name) {
            case 'id_number': return 'ID Number';
            case 'first_name': return 'First Name';
            case 'middle_name': return 'Middle Name';
            case 'last_name': return 'Last Name';
            case 'extension_name': return 'Extension Name';
            case 'dob': return 'Date of Birth';
            case 'age': return 'Age';
            case 'sex': return 'Sex';
            case 'purok_street': return 'Purok/Street';
            case 'barangay': return 'Barangay';
            case 'city_municipality': return 'City/Municipality';
            case 'province': return 'Province';
            case 'country': return 'Country';
            case 'zip_code': return 'Zip Code';
            case 'username': return 'Username';
            case 'email': return 'Email Address';
            case 'password': return 'Password';
            case 're_password': return 'Re-enter Password';
            default: return name;
        }
    };

    // ===== Helper function: AJAX validation =====
    const ajaxCheck = (field, url) => {
        const input = document.querySelector(`input[name="${field}"]`);
        if (!input) return;
        let debounceId;

        const runCheck = (value) => {
            if (!value) return;

            // Special handling for email: only allow specific domains before running AJAX uniqueness
            if (field === 'email') {
                // Must contain '@' and domain must be yahoo.com, email.com, or end with .edu or .edu.<cc>
                const atIdx = value.indexOf('@');
                if (atIdx === -1) {
                    setMessage(input, "Please include an @ in email address.", false);
                    return;
                }
                const domain = value.slice(atIdx + 1).toLowerCase();
                const allowed = (
                    domain === 'yahoo.com' ||
                    domain === 'email.com' ||
                    domain === 'gmail.com' ||
                    /(^|\.)edu(\.[a-z]{2})?$/.test(domain) ||
                    /\.edu(\.[a-z]{2})?$/.test(domain)
                );
                if (!allowed) {
                    setMessage(input, 'Please use an email from yahoo.com, email.com, gmail.com or a .edu domain.', false);
                    return;
                }
            }

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `${field}=${encodeURIComponent(value)}`
            })
            .then(res => res.json())
            .then(data => setMessage(input, data.message, data.available ?? data.valid))
            .catch(() => setMessage(input, "Server error. Please try again later.", false));
        };

        // Real-time (debounced) AJAX on input for immediate feedback
        input.addEventListener('input', () => {
            const value = input.value.trim();
            if (debounceId) clearTimeout(debounceId);
            debounceId = setTimeout(() => runCheck(value), 300);
        });

        // Fallback: also validate on blur
        input.addEventListener('blur', () => runCheck(input.value.trim()));
    };

    // ===== Helper function: validate Roman numeral =====
    const isValidRomanNumeral = (str) => {
        const romanRegex = /^M{0,3}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/i;
        return romanRegex.test(str);
    };

    // ===== Helper function: check for three consecutive identical letters =====
    const hasThreeConsecutiveLetters = (str) => {
        if (str.toUpperCase() === 'III') return false; // Exception for III
        const lower = str.toLowerCase();
        for (let i = 0; i < lower.length - 2; i++) {
            if (lower[i] === lower[i + 1] && lower[i + 1] === lower[i + 2] && lower[i] >= 'a' && lower[i] <= 'z') {
                return true;
            }
        }
        return false;
    };

    // ===== Name fields validation (match server rules) =====
    const nameFields = ['first_name', 'middle_name', 'last_name', 'extension_name'];
    nameFields.forEach(fieldName => {
        const input = document.querySelector(`input[name="${fieldName}"]`);
        const select = document.querySelector(`select[name="${fieldName}"]`);
        const field = input || select;
        if (!field) return;

        // Extension name is now a select dropdown - handle it differently
        if (fieldName === 'extension_name' && select) {
            // Handle select dropdown change
            select.addEventListener('change', () => {
                const value = select.value.trim();
                if (value === '') {
                    setMessage(select, '', true);
                } else {
                    setMessage(select, '', true);
                }
            });
            return; // Skip input validation for extension_name since it's now a select
        }

        // Skip if it's not an input field
        if (!input) return;

        // NO AUTO-CORRECTION - Let users type freely, show errors sequentially
        input.addEventListener('input', () => {
            const rawValue = input.value; // Don't trim yet - check raw value first
            const value = rawValue.trim();
            let message = '';
            let valid = true;

            // Optional fields: skip if empty
            if (value === '' && (fieldName === 'middle_name' || fieldName === 'extension_name')) {
                setMessage(input, '', true);
                return;
            }

            // SEQUENTIAL ERROR CHECKING - Show errors in priority order
            if (fieldName === 'extension_name') {
                // This shouldn't happen now since extension_name is a select, but keep for safety
                // Check first letter uppercase
                if (value.length > 0 && value[0] !== value[0].toUpperCase()) {
                    message = 'First letter must be uppercase.';
                    valid = false;
                }
                // Extension: letters only, no spaces
                else if (/[^A-Za-z]/.test(value)) {
                    message = `${fieldLabel(fieldName)} can only contain letters (no spaces or numbers).`;
                    valid = false;
                }
            } else {
                // First, Middle, Last - CHECK ERRORS IN SEQUENCE BASED ON WHAT'S PRESENT
                
                // Priority 1: Invalid characters (numbers, special chars) - Check first since it's most obvious
                if (/[^A-Za-z\s]/.test(value)) {
                    // Check what type of invalid character
                    if (/[0-9]/.test(value)) {
                        message = `${fieldLabel(fieldName)} cannot contain numbers.`;
                    } else if (/[@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(value)) {
                        message = `${fieldLabel(fieldName)} cannot contain special characters.`;
                    } else {
                        message = `${fieldLabel(fieldName)} can only contain letters and spaces.`;
                    }
                    valid = false;
                }
                // Priority 2: Double spaces (check rawValue to catch all double spaces including leading/trailing)
                else if (rawValue.includes('  ') || rawValue.match(/\s{2,}/)) {
                    message = `${fieldLabel(fieldName)} cannot contain double spaces.`;
                    valid = false;
                }
                // Priority 3: Three consecutive identical letters (check after spaces)
                else if (hasThreeConsecutiveLetters(value)) {
                    message = 'Three consecutive identical letters not allowed.';
                    valid = false;
                }
                // Priority 4: First letter must be capital
                else if (value.length > 0 && value[0] !== value[0].toUpperCase()) {
                    message = 'First letter must be capital.';
                    valid = false;
                }
                // Priority 5: Length validation
                else if (value.length < 2 || value.length > 50) {
                    message = `${fieldLabel(fieldName)} must be between 2 and 50 characters.`;
                    valid = false;
                }
                // Priority 6: Not all caps
                else if (/^[A-Z\s]+$/.test(value)) {
                    message = `${fieldLabel(fieldName)} cannot be all capital letters.`;
                    valid = false;
                }
                // Priority 7: Each word must start with capital
                else if (!/^[A-Z][a-z]*(\s[A-Z][a-z]*)*$/.test(value)) {
                    message = 'Only each word of the name must be capital.';
                    valid = false;
                }
            }

            setMessage(input, message, valid);
        });
    });

    // ===== Address Fields Validation =====
    const addressFields = [
        'purok_street', 'barangay', 'city_municipality', 'province', 'country', 'zip_code'
    ];

    addressFields.forEach(field => {
        const input = document.querySelector(`input[name="${field}"]`);
        if (!input) return;

        // NO AUTO-CORRECTION for zip_code - Let users type freely, validation will show errors
        // Removed auto-filtering - validation will handle it

        input.addEventListener('input', () => {
            const rawValue = input.value;
            const value = rawValue.trim();
            let message = '';
            let valid = true;

            const isPurokField = field === 'purok_street';
            const isPurokWord = /^Purok-?/i.test(value);
            const isPShortFormat = /^P\d+/.test(value); // e.g., P3, P12
            const isPurokFormat = isPurokField && (isPurokWord || isPShortFormat);
            const purokLabel = isPurokField ? (isPurokFormat ? 'Purok' : 'Street') : fieldLabel(field);

            // Check for three consecutive identical letters (only for purok format)
            if (isPurokFormat && hasThreeConsecutiveLetters(value)) {
                message = 'Three consecutive identical letters not allowed.';
                valid = false;
            }

            // Custom checks for Purok/Street
            if (field === 'barangay' && value.length > 0 && value[0] !== value[0].toUpperCase()) {
                message = 'First letter must be capital.';
                valid = false;
            } else if (field !== 'barangay' && value.length === 1 && /^[A-Z]$/.test(value)) {
                message = 'Each word must have at least 2 letters. Single letter words are not allowed.';
                valid = false;
            } else if (value.length > 0 && value[0] !== value[0].toUpperCase() && !(field === 'purok_street' && /^\d/.test(value))) {
                message = 'First letter must be uppercase.';
                valid = false;
            } else if (/^Purok\d+/.test(value)) {
                message = 'The "Purok" and the number should be separated with space (ex. Purok 1).';
                valid = false;
            } else if (/[^a-zA-Z0-9\s]/.test(value)) {
                message = 'Purok/Street must not contain any special character.';
                valid = false;
            } else if (value === '') {
                message = `${fieldLabel(field)} is required.`;
                valid = false;
            }
            else if (field === 'zip_code') {
                // Exactly 4 digits
                if (!/^[0-9]{4}$/.test(value)) {
                    message = `Zip Code must contain exactly 4 digits.`;
                    valid = false;
                }
            }
            else {
                // No leading spaces
                if (/^\s/.test(rawValue)) {
                    message = `${fieldLabel(field)} cannot start with a space.`;
                    valid = false;
                }
                // Allow letters, digits, spaces, and basic punctuation
                else if (/[^a-zA-Z0-9\s.,-]/.test(value)) {
                    message = `${fieldLabel(field)} contains invalid characters.`;
                    valid = false;
                }
                // For purok_street, check if it starts with a number FIRST (Case 2)
                if (field === 'purok_street' && /^\d/.test(value)) {
                    const parts = value.split(/\s+/);
                    const road = parts[1] || '';
                    if (!road) {
                        message = `Street that starts with a number have a word or road type after it (eg. 123 Street).`;
                        valid = false;
                    } else {
                        const roadWords = parts.slice(1);
                        if (!roadWords.every(w => /^[A-Z][a-z0-9]*$/.test(w))) {
                            message = `Each first letter in word in Street must be capitalized.`;
                            valid = false;
                        }
                    }
                }
                // Words and numbers must be separated by spaces (no letter-digit adjacency),
                // EXCEPT for Purok patterns like "Purok-3B" or shorthand P# like "P3"
                else if (valid &&
                    // Not in Purok format for the Purok/Street field (case-insensitive)
                    !(field === 'purok_street' && (/^Purok-/i.test(value) || /^P\d+/i.test(value)))
                    // Contains a digit immediately followed by a letter
                    && /[0-9][A-Za-z]/.test(value)
                    // Allow ordinals like 1st, 2nd, 3rd, 4th ONLY for Street context
                    && !(
                        field === 'purok_street' && !( /^Purok-/i.test(value) || /^P\d+/i.test(value) ) && /\b\d+(st|nd|rd|th)\b/i.test(value)
                    )
                ) {
                    message = `It should not contain letters after a number unless it is ordinal (ex. 1st, 2nd, 3rd, 4th,...).`;
                    valid = false;
                }
                // Strict ordinal validation for Street: if an ordinal exists, its suffix must be correct
                else if (valid &&
                    field === 'purok_street' && !( /^Purok-/i.test(value) || /^P\d+/i.test(value) )
                ) {
                    const ordinalRegex = /\b(\d+)(st|nd|rd|th)\b/ig;
                    let m;
                    let badOrdinal = false;
                    while ((m = ordinalRegex.exec(value)) !== null) {
                        const num = parseInt(m[1], 10);
                        const suf = m[2].toLowerCase();
                        const lastTwo = num % 100;
                        let correct = 'th';
                        if (lastTwo < 11 || lastTwo > 13) {
                            const last = num % 10;
                            if (last === 1) correct = 'st';
                            else if (last === 2) correct = 'nd';
                            else if (last === 3) correct = 'rd';
                        }
                        if (suf !== correct) { badOrdinal = true; break; }
                    }
                    if (badOrdinal) {
                        message = `Invalid ordinal format. Use correct suffix (e.g., 1st, 2nd, 3rd, 4th, 21st).`;
                        valid = false;
                    }
                }
                else if (valid && !(field === 'purok_street' && (/^Purok-/i.test(value) || /^P\d+/i.test(value))) && /[A-Za-z][0-9]/.test(value)) {
                    message = `${purokLabel} must have words and numbers separated by a space (e.g., 123 Street).`;
                    valid = false;
                } else {
                    if (field === 'purok_street' && valid) {
                        // If user types P#/P-#/P # (e.g., P3, P-3, P 3), treat as Purok attempt but require full word
                        if (/^[Pp](?:\s*-\s*|\s*)\d+/.test(value)) {
                            message = `Please write "Purok" completely (e.g., Purok 3B).`;
                            valid = false;
                        }
                        // If user types 'Purok' followed by a letter
                        else if (/^Purok[A-Za-z]/i.test(value)) {
                            message = `Purok format must be 'Purok <number>' (ex. Purok 1).`;
                            valid = false;
                        }
                        // If user types Purok- followed by a letter (e.g., Purok-B), require a number after the dash
                        else if (/^Purok-[A-Za-z]/i.test(value)) {
                            message = `Purok must have a number immediately after "Purok-" (e.g., Purok 3B).`;  
                            valid = false;
                        }
                        // If user types Purok-# with more than one letter after the number (e.g., Purok-1Bqeqasd), disallow
                        else if (/^Purok-\d+[A-Za-z]{2,}/i.test(value)) {
                            message = `Purok can contain at most one letter after the number (e.g., Purok-3B).`;
                            valid = false;
                        }
                        // Case 1: Purok-# or Purok-#A, with number up to 3 digits and <= 100
                        else if (/^Purok-\d+[A-Za-z]?$/.test(value) || /^Purok-\d+[A-Za-z]?$/i.test(value)) {
                            const m = value.match(/^Purok-(\d+)([A-Za-z]?)$/i);
                            if (m) {
                                const numPart = m[1];
                                const numVal = parseInt(numPart, 10);
                                if (numPart.length > 3 || numVal > 100) {
                                    message = "Purok number should not exceed 3 characters (e.g., Purok-1, Purok-3B).";
                                    valid = false;
                                }
                            }
                            // otherwise valid, nothing more to check
                        }
                        // Generic capitalized-words rule
                        if (valid) {
                            const normalized = value.replace(/[.,-]/g, ' ');
                            // Allow either capitalized words (e.g., Purok, Street) or purely numeric words (e.g., 1)
                            if (!/^(?:[A-Z][a-z0-9]*|\d+)(\s(?:[A-Z][a-z0-9]*|\d+))*$/.test(normalized)) {
                                message = `Each word in ${purokLabel} must start with a capital letter or be a number (e.g., Purok 1).`;
                                valid = false;
                            }
                        }

                        // Each alphabetic word must have at least 2 letters
                        if (valid) {
                            const words = value.split(/\s+/);
                            for (const w of words) {
                                if (/^[A-Za-z]+$/.test(w) && w.length === 1) {
                                    message = `${purokLabel} cannot contain single-letter words.`;
                                    valid = false;
                                    break;
                                }
                            }
                        }
                    } else if (field === 'barangay') {
                        // Custom barangay validation
                        if (value.length > 0 && value[0] !== value[0].toUpperCase()) {
                            message = 'First letter must be capital.';
                            valid = false;
                        } else if (!value.startsWith('Barangay ')) {
                            message = 'Format must start with "Barangay" followed by a space and number or letter.';
                            valid = false;
                        } else {
                            const after = value.substring(9).trim();
                            if (after.length === 0) {
                                message = 'Barangay must be followed by a word.';
                                valid = false;
                            } else if (!/^[A-Za-z0-9]+(\s+[A-Za-z0-9]+)*$/.test(after)) {
                                message = 'Barangay must be followed by a number or word.';
                                valid = false;
                            } else {
                                // Check for single letter words
                                const words = after.split(/\s+/);
                                for (const w of words) {
                                    if (/^[A-Za-z]+$/.test(w) && w.length === 1) {
                                        message = 'Each word must have at least 2 letters. Single letter words are not allowed.';
                                        valid = false;
                                        break;
                                    }
                                }
                            }
                        }
                    } else {
                        // Other address fields: capitalized words rule
                        const normalized = value.replace(/[.,-]/g, ' ');
                        // Check if all letters are uppercase (all caps) - only for country
                        if (field === 'country' && /^[A-Z\s0-9]*$/.test(normalized) && normalized.replace(/[^A-Z]/g, '').length > 0) {
                            message = 'All capital letters not allowed (ex. Usa).';
                            valid = false;
                        } else if (!/^[A-Z][a-z0-9]*(\s[A-Z][a-z0-9]*)*$/.test(normalized)) {
                            message = `Each word in ${fieldLabel(field)} must start with a capital letter followed by small letters.`;
                            valid = false;
                        }
                    }
                }
            }
            setMessage(input, message, valid);
        });
    });

    // ===== Barangay focus message =====
    const barangayInput = document.querySelector('input[name="barangay"]');
    if (barangayInput) {
        barangayInput.addEventListener('focus', function() {
            if (!this.value.trim()) {
                setMessage(this, 'Format must start with the word "Barangay" followed by a number or word.', false);
            }
        });
        barangayInput.addEventListener('blur', function() {
            if (!this.value.trim()) {
                setMessage(this, '', true);
            }
        });
    }

    // ===== Auto-compute age from DOB =====
    const dobInput = document.getElementById('dob');
    const ageField = document.getElementById('age');
    if (dobInput && ageField) {
        const calculateAge = () => {
            const dobValue = dobInput.value;

            // If DOB is cleared, also clear age and any previous messages
            if (!dobValue) {
                ageField.value = '';
                setMessage(dobInput, "", true);
                setMessage(ageField, "", true);
                return;
            }

            // Clear any stale "can't be empty" message once a date is selected
            setMessage(dobInput, "", true);

            const dob = new Date(dobValue);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
            ageField.value = age;

            // Check age requirement and show message in Age field
            if (age < 18) {
                setMessage(ageField, "You must be at least 18 years old to register, please try again.", false);
            } else {
                setMessage(ageField, "", true);
            }
        };
        dobInput.addEventListener('change', calculateAge);
        dobInput.addEventListener('input', calculateAge);
        dobInput.addEventListener('blur', calculateAge);
    }

    // ===== Sex field inline validation (clear message when selected) =====
    const sexSelect = document.querySelector('select[name="sex"]');
    if (sexSelect) {
        sexSelect.addEventListener('change', () => {
            if (sexSelect.value.trim() !== '') {
                setMessage(sexSelect, "", true);
            }
        });
    }

    // ===== Password progressive validation and strength =====
    const passwordInput = document.querySelector('input[name="password"]');
    const rePasswordInput = document.querySelector('input[name="re_password"]');
    const strengthBar = document.querySelector('.strength-bar');

    // Check if this is the login page
    const isLoginPage = !!document.querySelector('button[name="login_btn"]');

    if (passwordInput && !isLoginPage) {
        passwordInput.addEventListener('input', () => {
            const val = passwordInput.value;
            let strength = 0;
            if (val.length >= 8) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[0-9]/.test(val)) strength++;
            if (/[\W]/.test(val)) strength++;

            let strengthText = '';
            if (val.length >= 8 && /[A-Z]/.test(val) && /[a-z]/.test(val) && /[0-9]/.test(val) && /[\W]/.test(val)) {
                strengthText = 'Strong Password';
            } else if (val.length > 0) {
                strengthText = 'Weak Password';
            }

            let message = '';
            let valid = true;

            if (val.length < 8) {
                message = 'Password must be at least 8 characters long.';
                valid = false;
            } else if (!/[A-Z]/.test(val)) {
                message = 'Password must contain at least 1 uppercase letter.';
                valid = false;
            } else if (!/[a-z]/.test(val)) {
                message = 'Password must contain at least 1 lowercase letter.';
                valid = false;
            } else if (!/[0-9]/.test(val)) {
                message = 'Password must contain at least 1 number.';
                valid = false;
            } else if (!/[\W]/.test(val)) {
                message = 'Password must contain at least 1 special character (!@#$%^&*).';
                valid = false;
            }

            // Set strength text above the field
            const inputBox = passwordInput.closest('.input-box');
            if (inputBox) {
                const label = inputBox.querySelector('label');
                if (label) {
                    let strengthEl = label.querySelector('.strength-text');
                    if (!strengthEl) {
                        strengthEl = document.createElement('span');
                        strengthEl.className = 'strength-text';
                        strengthEl.style.marginLeft = '10px';
                        strengthEl.style.fontWeight = 'bold';
                        label.appendChild(strengthEl);
                    }
                    strengthEl.textContent = strengthText;
                    strengthEl.style.color = strengthText === 'Strong Password' ? 'green' : strengthText === 'Medium Password' ? 'orange' : 'red';
                }
            }

            setMessage(passwordInput, message, valid);

            if (strengthBar) {
                strengthBar.style.width = (strength * 25) + '%';
                let color = strength <= 1 ? 'red' : strength <= 3 ? 'orange' : 'green';
                strengthBar.style.backgroundColor = color;
            }
        });

        // AJAX check to ensure new password is not the same as current password (only on change_password.php)
        const isChangePasswordPage = !!document.querySelector('button[name="change_password_btn"]');
        if (isChangePasswordPage) {
            passwordInput.addEventListener('blur', () => {
                const val = passwordInput.value;
                if (!val) return;

                fetch('../server/check_new_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'password=' + encodeURIComponent(val)
                })
                .then(res => res.json())
                .then(data => {
                    // If invalid, show the server error; if valid, keep the existing strength message intact
                    if (!data.valid) {
                        setMessage(passwordInput, data.message || "New Password can't be the current password.", false);
                    }
                })
                .catch(() => {
                    // Silent fail for network/server errors; do not block user
                });
            });
        }
    }

    if (rePasswordInput && passwordInput) {
        const guardIfPasswordEmpty = (e) => {
            if (!passwordInput.value) {
                if (e) e.preventDefault();
                setMessage(rePasswordInput, "Please enter your password first.", false);
                passwordInput.focus();
                return true;
            }
            return false;
        };

        rePasswordInput.addEventListener('mouseenter', () => {
            if (!passwordInput.value) {
                setMessage(rePasswordInput, "Please enter your password first.", false);
            }
        });

        rePasswordInput.addEventListener('focus', (e) => {
            if (guardIfPasswordEmpty(e)) {
                rePasswordInput.blur();
            }
        });

        rePasswordInput.addEventListener('beforeinput', (e) => {
            guardIfPasswordEmpty(e);
        });

        rePasswordInput.addEventListener('paste', (e) => {
            guardIfPasswordEmpty(e);
        });

        rePasswordInput.addEventListener('input', () => {
            if (!passwordInput.value && rePasswordInput.value) {
                setMessage(rePasswordInput, "Please enter your password first.", false);
                return;
            }
            const match = rePasswordInput.value === passwordInput.value;
            setMessage(
                rePasswordInput,
                match ? "Passwords match." : "Passwords do not match.",
                match
            );
        });

        rePasswordInput.addEventListener('blur', () => {
            if (!passwordInput.value && rePasswordInput.value) {
                setMessage(rePasswordInput, "Please enter your password first.", false);
                passwordInput.focus();
            }
        });
    }

    document.querySelectorAll('.input-box i.bx-eye, .input-box i.bx-eye-slash').forEach(icon => {
        icon.addEventListener('click', () => {
            const box = icon.closest('.input-box');
            if (!box) return;

            const input = box.querySelector('input[type="password"], input[type="text"]');
            if (!input) return;

            input.type = input.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('bx-eye');
            icon.classList.toggle('bx-eye-slash');
        });
    });

    // ===== Username live format + AJAX checks =====
    const usernameInput = document.querySelector('input[name="username"]');
    if (usernameInput) {
        // Focus event: show initial error message
        usernameInput.addEventListener('focus', () => {
            if (!usernameInput.value.trim()) {
                setMessage(usernameInput, 'Username format must have one dot between names (e.g., juan.dela).', false);
            }
        });

        // NO AUTO-CORRECTION - Let users type freely, show errors instead
        // Removed auto-filtering - validation will show errors sequentially

        usernameInput.addEventListener('input', () => {
            const value = usernameInput.value.trim();
            if (value === '') {
                setMessage(usernameInput, 'Username format must have one dot between names (e.g., juan.dela).', false);
                return;
            }

            // Check for three consecutive identical letters
            if (hasThreeConsecutiveLetters(value)) {
                setMessage(usernameInput, 'Three consecutive identical letters not allowed.', false);
                return;
            }

            // If no dot: require dot after first word
            if (!value.includes('.')) {
                setMessage(usernameInput, ' One dot "." is required after the first word.', false);
                return;
            }

            // If has dot but not valid format: require letter after dot
            const finalPattern = /^[a-z]+\.[a-z]+$/;
            if (!finalPattern.test(value)) {
                setMessage(usernameInput, 'Letter must come after the one dot.', false);
                return;
            }

            setMessage(usernameInput, '', true);
        });

        // AJAX uniqueness check on blur (only if format is valid)
        usernameInput.addEventListener('blur', () => {
            const value = usernameInput.value.trim();
            const finalPattern = /^[a-z]+\.[a-z]+$/;
            if (!finalPattern.test(value)) return;

            fetch('../server/check_username.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `username=${encodeURIComponent(value)}`
            })
            .then(res => res.json())
            .then(data => setMessage(usernameInput, data.message, data.available ?? data.valid))
            .catch(() => setMessage(usernameInput, 'Server error. Please try again later.', false));
        });
    }

    // ===== AJAX checks for email =====
    ajaxCheck('email', '../server/check_email.php');

    // Email length validation: 5–70 characters
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('input', () => {
            const val = emailInput.value.trim();
            if (val === '') {
                setMessage(emailInput, '', true);
                return;
            }
            if (!val.includes('@')) {
                setMessage(emailInput, "Please include an @ in email address.", false);
                return;
            }
            if (val.length < 5 || val.length > 70) {
                setMessage(emailInput, 'Email must be between 5 and 70 characters.', false);
            } else {
                setMessage(emailInput, '', true);
            }
        });
    }

    // ===== Form submit validation =====
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', e => {
            // Ensure age is calculated before validation
            if (dobInput && dobInput.value && ageField && ageField.value === '') {
                const dobValue = dobInput.value;
                const dob = new Date(dobValue);
                const today = new Date();
                let age = today.getFullYear() - dob.getFullYear();
                const m = today.getMonth() - dob.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
                ageField.value = age;
            }
            // 1) Generic required fields empty check (excluding optional: middle_name, extension_name, and handling sex separately)
            const requiredNames = [
                'first_name','last_name','dob','age','username','email','password','re_password',
                'purok_street','barangay','city_municipality','province','country','zip_code'
            ];

            let firstInvalid = null;
            requiredNames.forEach(name => {
                const el = registerForm.querySelector(`[name="${name}"]`);
                if (!el) return;
                const val = (el.value || '').trim();
                if (val === '') {
                    e.preventDefault();
                    setMessage(el, `${fieldLabel(name)} can't be empty.`, false);
                    console.log('Registration prevented: empty field ->', name);
                    if (!firstInvalid) firstInvalid = el;
                }
            });

            // 2) Sex selection specific check and message
            const sexField = registerForm.querySelector('select[name="sex"]');
            if (sexField && sexField.value.trim() === "") {
                e.preventDefault();
                setMessage(sexField, "Please choose a sex.", false);
                console.log('Registration prevented: sex not selected');
                if (!firstInvalid) firstInvalid = sexField;
            }

            // 3) Age policy check (must be >= 18)
            if (ageField) {
                const ageVal = parseInt(ageField.value);
                if (isNaN(ageVal) || ageVal < 18) {
                    e.preventDefault();
                    setMessage(ageField, "You must be at least 18 years old.", false);
                    console.log('Registration prevented: invalid age ->', ageField.value);
                    if (!firstInvalid) firstInvalid = ageField;
                }
            }

            // 4) Passwords must match
            if (passwordInput && rePasswordInput && passwordInput.value !== rePasswordInput.value) {
                e.preventDefault();
                setMessage(rePasswordInput, "Passwords do not match.", false);
                if (!firstInvalid) firstInvalid = rePasswordInput;
            }

            // 5) Check if age is calculated
            if (ageField && ageField.value === '') {
                e.preventDefault();
                setMessage(ageField, "Please select a valid date of birth to calculate age.", false);
                if (!firstInvalid) firstInvalid = ageField;
            }

            if (firstInvalid) firstInvalid.focus();
        });
    }

    // ===== ID Number validation (format xxxx-xxxx) + AJAX with instant feedback (register only) =====
    const idInputField = document.querySelector('input[name="id_number"]');
    if (idInputField && !idInputField.readOnly) {
        const isForgotStep1 = !!document.querySelector('button[name="forgot_step1_btn"]');
        const validateIdFormat = (value) => /^\d{4}-\d{4}$/.test(value);
        let ajaxTimeout;

        // NO AUTO-CORRECTION for ID - Let users type freely, validation will show format errors
        // Removed auto-formatting - validation will handle format checking

        idInputField.addEventListener('input', () => {
            const value = idInputField.value.trim();

            // Format validation
            if (value === '') {
                setMessage(idInputField, '', true);
                return;
            }
            if (!validateIdFormat(value)) {
                setMessage(idInputField, 'Please enter ID in format: xxxx-xxxx', false);
                return;
            }

            setMessage(idInputField, '', true);

            // Debounced AJAX check
            if (ajaxTimeout) clearTimeout(ajaxTimeout);
            ajaxTimeout = setTimeout(() => {
                // On forgot password, skip availability messages entirely
                if (isForgotStep1) return;
                fetch('../server/check_id.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id_number=${encodeURIComponent(value)}`
                })
                .then(res => res.json())
                .then(data => setMessage(idInputField, data.message, data.available ?? data.valid))
                .catch(() => setMessage(idInputField, "Server error. Please try again later.", false));
            }, 300); // Wait 300ms after typing stops
        });
    }

    // ===== Forgot Password: Step 2 answer confirmation =====
    const forgotStep2Button = document.querySelector('button[name="forgot_step2_btn"]');
    if (forgotStep2Button) {
        const forgotForm = forgotStep2Button.closest('form');
        if (forgotForm) {
            // Real-time validation for answers to match security question rules
            const answerInputs = Array.from(forgotForm.querySelectorAll('input[name^="answers["]'));
            const validRe = /^[A-Za-z0-9 .'-]{3,}$/; // allowed chars and min 3

            answerInputs.forEach(input => {
                input.addEventListener('input', () => {
                    const val = (input.value || '').trim();
                    if (val.length === 0) {
                        setMessage(input, '', true);
                        return;
                    }
                    if (val.length < 3) {
                        setMessage(input, 'Answer must be at least 3 characters.', false);
                        return;
                    }

                    // Check for three consecutive identical letters
                    if (hasThreeConsecutiveLetters(val)) {
                        setMessage(input, 'Three consecutive identical letters not allowed.', false);
                        return;
                    }

                    if (!validRe.test(val)) {
                        setMessage(input, "Only letters, numbers, spaces, periods (.), apostrophes ('), and hyphens (-) are allowed.", false);
                        return;
                    }
                    setMessage(input, '', true);
                });
            });

            forgotForm.addEventListener('submit', (e) => {
                // If the submission was triggered by the Back button, skip JS validation
                try {
                    if ((e && e.submitter && e.submitter.name === 'forgot_back_btn') || forgotForm.dataset.skipValidation === '1') {
                        // allow the form to submit so server can handle the back action
                        return;
                    }
                } catch (err) {
                    // ignore and continue with validation for older browsers
                }

                // Ensure all answers present and valid
                for (const input of answerInputs) {
                    const val = (input.value || '').trim();
                    if (val.length < 3) {
                        e.preventDefault();
                        setMessage(input, 'Answer must be at least 3 characters.', false);
                        input.focus();
                        return;
                    }
                    if (!/^[A-Za-z0-9 .'-]{3,}$/.test(val)) {
                        e.preventDefault();
                        setMessage(input, "Only letters, numbers, spaces, periods (.), apostrophes ('), and hyphens (-) are allowed.", false);
                        input.focus();
                        return;
                    }
                }
            });
        }
    }
});

// Disable right-click globally
document.addEventListener('contextmenu', (e) => e.preventDefault());

// Reset login attempts on successful login function 
function resetLoginAttempts() {
    localStorage.removeItem('login_attempts');
}
