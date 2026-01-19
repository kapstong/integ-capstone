<?php
/**
 * ATIERA Financial Management System - Modern Datepicker Component
 * Include this file to automatically enhance all date inputs with a beautiful calendar picker
 */
?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- Custom Datepicker Styles -->
<style>
.flatpickr-input {
    background: white;
    border: 1px solid #cbd5e0;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.2s;
    cursor: pointer;
}

.flatpickr-input:hover {
    border-color: #2342a6;
}

.flatpickr-input:focus {
    outline: none;
    border-color: #2342a6;
    box-shadow: 0 0 0 3px rgba(35, 66, 166, 0.1);
}

.flatpickr-calendar {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
}

.flatpickr-months {
    background: linear-gradient(135deg, #1b2f73 0%, #2342a6 100%);
    border-radius: 0.75rem 0.75rem 0 0;
}

.flatpickr-current-month {
    color: white !important;
}

.flatpickr-months .flatpickr-prev-month,
.flatpickr-months .flatpickr-next-month {
    fill: white !important;
}

.flatpickr-months .flatpickr-prev-month:hover svg,
.flatpickr-months .flatpickr-next-month:hover svg {
    fill: #d4af37 !important;
}

.flatpickr-weekday {
    color: #2342a6 !important;
    font-weight: 600;
}

.flatpickr-day {
    border-radius: 0.5rem;
    font-weight: 500;
}

.flatpickr-day.today {
    border-color: #2342a6;
    background: #eef2ff;
    color: #2342a6;
}

.flatpickr-day.today:hover {
    background: #2342a6;
    color: white;
}

.flatpickr-day.selected {
    background: linear-gradient(135deg, #1b2f73 0%, #2342a6 100%);
    border-color: #1b2f73;
}

.flatpickr-day.selected:hover {
    background: linear-gradient(135deg, #2342a6 0%, #1b2f73 100%);
}

.flatpickr-day:hover {
    background: #f3f4f6;
    border-color: #cbd5e0;
}

/* Add calendar icon to date inputs */
.date-input-wrapper {
    position: relative;
    display: inline-block;
    width: 100%;
}

.date-input-wrapper::after {
    content: "\f073";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    pointer-events: none;
}

.date-input-wrapper input {
    padding-right: 3rem;
}
</style>

<!-- Datepicker Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize datepicker on all date inputs
    initializeDatePickers();

    // Watch for dynamically added date inputs
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) {
                    initializeDatePickers(node);
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});

function initializeDatePickers(container) {
    container = container || document;

    // Find all date inputs
    const dateInputs = container.querySelectorAll('input[type="date"]:not(.flatpickr-input), input[data-date]:not(.flatpickr-input)');

    dateInputs.forEach(function(input) {
        // Wrap input with calendar icon
        if (!input.parentElement.classList.contains('date-input-wrapper')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'date-input-wrapper';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
        }

        // Get configuration from data attributes
        const config = {
            dateFormat: input.dataset.format || 'Y-m-d',
            altInput: true,
            altFormat: input.dataset.altFormat || 'F j, Y',
            allowInput: true,
            clickOpens: true,
            defaultDate: input.value || null,
            minDate: input.min || input.dataset.minDate || null,
            maxDate: input.max || input.dataset.maxDate || null,
            enable: input.dataset.enableDates ? JSON.parse(input.dataset.enableDates) : undefined,
            disable: input.dataset.disableDates ? JSON.parse(input.dataset.disableDates) : undefined,
            mode: input.dataset.mode || 'single', // single, multiple, range
            onChange: function(selectedDates, dateStr, instance) {
                // Trigger change event for form validation
                input.dispatchEvent(new Event('change', { bubbles: true }));

                // Call custom callback if defined
                if (input.dataset.onDateChange) {
                    // Dispatch custom event with data instead of using eval()
                    const event = new CustomEvent('datechange', {
                        detail: { selectedDates, dateStr, instance },
                        bubbles: true,
                        cancelable: true
                    });
                    input.dispatchEvent(event);
                }
            },
            onReady: function(selectedDates, dateStr, instance) {
                // Add custom class if specified
                if (input.dataset.theme) {
                    instance.calendarContainer.classList.add('flatpickr-' + input.dataset.theme);
                }
            }
        };

        // Initialize Flatpickr
        flatpickr(input, config);

        // Store instance for later access
    });
}

// Utility function to get date picker instance
function getDatePicker(element) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    return element && element._flatpickr ? element._flatpickr : null;
}

// Global helper functions
window.initializeDatePickers = initializeDatePickers;
window.getDatePicker = getDatePicker;
</script>

<?php
/**
 * Usage Examples:
 *
 * Basic date input (automatically enhanced):
 * <input type="date" name="start_date" class="form-control">
 *
 * Date input with custom format:
 * <input type="date" name="end_date" class="form-control"
 *        data-format="Y-m-d"
 *        data-alt-format="F j, Y">
 *
 * Date range picker:
 * <input type="text" name="date_range" class="form-control"
 *        data-date
 *        data-mode="range"
 *        data-alt-format="M j, Y">
 *
 * Date input with min/max dates:
 * <input type="date" name="appointment_date" class="form-control"
 *        data-min-date="today"
 *        data-max-date="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
 *
 * Date input with disabled dates (weekends):
 * <input type="date" name="business_date" class="form-control"
 *        data-disable-dates='[{"from":"","to":"","daysOfWeek":[0,6]}]'>
 *
 * Date input with custom callback:
 * <input type="date" name="event_date" class="form-control"
 *        data-on-date-change="handleDateChange">
 *
 * <script>
 * function handleDateChange(selectedDates, dateStr, instance) {
 *     // Handle date change
 * }
 * </script>
 */
?>
