// =========================================================
// SECURITY QUESTIONS - DUPLICATE PREVENTION
// =========================================================

document.addEventListener('DOMContentLoaded', function() {
    const questionSelects = document.querySelectorAll('.question-select');
    const answerIds = ['answer_1', 'answer_2', 'answer_3'];
    
    /**
     * Update dropdown options to disable already selected questions
     */
    function updateQuestionOptions() {
        // Get all currently selected questions
        const selectedQuestions = Array.from(questionSelects)
            .map(select => select.value)
            .filter(value => value !== '');
        
        // Update each dropdown
        questionSelects.forEach(currentSelect => {
            Array.from(currentSelect.options).forEach(option => {
                // Don't disable the empty option
                if (option.value === '') {
                    option.disabled = false;
                    return;
                }
                
                // Check if this option is selected in any other dropdown
                const isSelectedElsewhere = selectedQuestions.includes(option.value) && 
                                           currentSelect.value !== option.value;
                
                option.disabled = isSelectedElsewhere;
            });
        });
    }
    
    // Tie each question select to its corresponding answer field index
    function indexForSelect(select) {
        if (!select || !select.id) return -1;
        const m = select.id.match(/question_(\d+)/);
        return m ? (parseInt(m[1], 10) - 1) : -1;
    }

    function getAnswerElByIndex(idx) {
        const id = answerIds[idx];
        return document.getElementById(id);
    }

    function enforceAnswerStateFor(select) {
        const idx = indexForSelect(select);
        if (idx < 0) return;
        const ans = getAnswerElByIndex(idx);
        if (!ans) return;
        const chosen = (select.value || '') !== '';
        ans.disabled = !chosen;
        if (!chosen) {
            // Clear any existing input and show error hint
            ans.value = '';
            showMessage(ans, 'Please select a question first.');
        } else {
            clearMessage(ans);
        }
    }

    // Add change event listener to all question selects with inline validation
    questionSelects.forEach(select => {
        select.addEventListener('change', () => {
            updateQuestionOptions();
            enforceAnswerStateFor(select);
            const value = select.value;
            if (value === '') {
                showMessage(select, 'Please select a question.');
            } else {
                clearMessage(select);
            }
        });
    });
    
    // Initial update on page load: update options and enforce disabled answers
    updateQuestionOptions();
    questionSelects.forEach(select => enforceAnswerStateFor(select));
    
    // --- Inline / Real-time validation for answers ---
    // already declared above
    const validRe = /^[A-Za-z0-9 .'-]{3,}$/; // allowed chars and min 3 chars

    function getMessageEl(inputEl) {
        if (!inputEl) return null;
        // small.input-message is the next sibling in our markup
        const next = inputEl.nextElementSibling;
        if (next && next.classList && next.classList.contains('input-message')) return next;
        // fallback: search within parent
        return inputEl.parentElement ? inputEl.parentElement.querySelector('.input-message') : null;
    }

    function showMessage(inputEl, message) {
        const msgEl = getMessageEl(inputEl);
        if (msgEl) {
            msgEl.textContent = message;
            msgEl.classList.add('show');
        } else {
            // fallback to title
            inputEl.title = message;
        }
        inputEl.setAttribute('aria-invalid', 'true');
    }

    function clearMessage(inputEl) {
        const msgEl = getMessageEl(inputEl);
        if (msgEl) {
            msgEl.textContent = '';
            msgEl.classList.remove('show');
        } else {
            inputEl.title = '';
        }
        inputEl.removeAttribute('aria-invalid');
    }

    // Attach real-time listeners
    answerIds.forEach((id, index) => {
        const el = document.getElementById(id);
        if (!el) return;

        // Prevent interaction if corresponding question not chosen
        const select = document.getElementById('question_' + (index + 1));
        const guardIfNoQuestion = (e) => {
            if (!select || (select.value || '') !== '') return false;
            if (e) e.preventDefault();
            showMessage(el, 'Please select a question first.');
            select.focus();
            return true;
        };

        el.addEventListener('focus', (e) => {
            if (guardIfNoQuestion(e)) el.blur();
        });
        el.addEventListener('beforeinput', (e) => {
            guardIfNoQuestion(e);
        });
        el.addEventListener('paste', (e) => {
            guardIfNoQuestion(e);
        });

        el.addEventListener('input', function() {
            const value = (el.value || '').trim();

            if (value.length === 0) {
                clearMessage(el);
                return;
            }

            if (value.length < 3) {
                showMessage(el, 'Answer must be at least 3 characters.');
                return;
            }

            if (!validRe.test(value)) {
                showMessage(el, "Only letters, numbers, spaces, periods (.), apostrophes ('), and hyphens (-) are allowed.");
                return;
            }

            clearMessage(el);
        });
    });
    
    /**
     * Form submission validation
     */
    const securityForm = document.getElementById('securityForm');
    if (securityForm) {
        securityForm.addEventListener('submit', function(e) {
            const questions = Array.from(questionSelects).map(select => select.value);

            // Build trimmed answers and references
            const answers = answerIds.map(id => ({
                id,
                el: document.getElementById(id),
                value: (document.getElementById(id).value || '').trim()
            }));

            // Clear any previous messages
            answers.forEach(a => a.el && clearMessage(a.el));
            questionSelects.forEach(s => clearMessage(s));

            // Check for empty questions
            if (questions.includes('')) {
                e.preventDefault();
                // Show inline messages and focus first empty select
                for (let i = 0; i < questionSelects.length; i++) {
                    if (questionSelects[i].value === '') {
                        showMessage(questionSelects[i], 'Please select a question.');
                        questionSelects[i].focus();
                        break;
                    }
                }
                return false;
            }

            if (new Set(questions).size !== questions.length) {
                e.preventDefault();
                alert('⚠️ Please select different questions. Duplicate questions are not allowed.');
                return false;
            }

            // Validate answers; focus first invalid
            for (let i = 0; i < answers.length; i++) {
                const { el, value } = answers[i];

                if (!value) {
                    e.preventDefault();
                    showMessage(el, `Please provide an answer for Question ${i + 1}.`);
                    el.focus();
                    return false;
                }

                if (value.length < 3) {
                    e.preventDefault();
                    showMessage(el, 'Answer must be at least 3 characters.');
                    el.focus();
                    return false;
                }

                if (!validRe.test(value)) {
                    e.preventDefault();
                    showMessage(el, "Only letters, numbers, spaces, periods (.), apostrophes ('), and hyphens (-) are allowed.");
                    el.focus();
                    return false;
                }
            }

            return true;
        });
    }
});
