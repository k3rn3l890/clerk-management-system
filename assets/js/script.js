/**
 * Ghana Court Clerk Management System
 * Custom JavaScript
 */

// Document Ready
$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-dismiss alerts
    setTimeout(function() {
        $('.alert-dismissible').alert('close');
    }, 5000);

    // Case search functionality
    $('#caseSearchForm').on('submit', function(e) {
        e.preventDefault();
        var searchTerm = $('#caseSearchInput').val();
        
        if (searchTerm.trim() !== '') {
            window.location.href = 'cases.php?search=' + encodeURIComponent(searchTerm);
        }
    });

    // Confirm delete
    $('.confirm-delete').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
        }
    });

    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        var input = $($(this).attr('toggle'));
        if (input.attr('type') == 'password') {
            input.attr('type', 'text');
            $(this).html('<i class="fas fa-eye-slash"></i>');
        } else {
            input.attr('type', 'password');
            $(this).html('<i class="fas fa-eye"></i>');
        }
    });

    // File input preview
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
        
        // Image preview
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').attr('src', e.target.result).show();
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Dynamic form fields
    let partyCounter = 0;
    
    // Add party button
    $('#addParty').on('click', function() {
        partyCounter++;
        
        const newParty = `
            <div class="party-row mb-3 border p-3 rounded">
                <div class="row">
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label for="partyName${partyCounter}" class="form-label required-field">Party Name</label>
                            <input type="text" class="form-control" id="partyName${partyCounter}" name="party_name[]" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="partyType${partyCounter}" class="form-label required-field">Party Type</label>
                            <select class="form-select" id="partyType${partyCounter}" name="party_type[]" required>
                                <option value="">Select Type</option>
                                <option value="plaintiff">Plaintiff</option>
                                <option value="defendant">Defendant</option>
                                <option value="appellant">Appellant</option>
                                <option value="respondent">Respondent</option>
                                <option value="witness">Witness</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="partyContact${partyCounter}" class="form-label">Contact Info</label>
                            <input type="text" class="form-control" id="partyContact${partyCounter}" name="party_contact[]">
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-danger remove-party"><i class="fas fa-times"></i> Remove</button>
            </div>
        `;
        
        $('#partiesContainer').append(newParty);
    });
    
    // Remove party button
    $(document).on('click', '.remove-party', function() {
        $(this).closest('.party-row').remove();
    });

    // Case type dependent fields
    $('#caseType').on('change', function() {
        const caseType = $(this).val();
        
        // Show/hide fields based on case type
        if (caseType === 'criminal') {
            $('.criminal-fields').show();
            $('.civil-fields').hide();
        } else if (caseType === 'civil') {
            $('.criminal-fields').hide();
            $('.civil-fields').show();
        } else {
            $('.criminal-fields, .civil-fields').hide();
        }
    });

    // Mark notification as read
    $('.notification-link').on('click', function() {
        const notificationId = $(this).data('id');
        
        $.ajax({
            url: 'ajax/mark_notification_read.php',
            type: 'POST',
            data: { notification_id: notificationId },
            success: function(response) {
                // Update UI if needed
            }
        });
    });

    // Mark message as read
    $('.message-link').on('click', function() {
        const messageId = $(this).data('id');
        
        $.ajax({
            url: 'ajax/mark_message_read.php',
            type: 'POST',
            data: { message_id: messageId },
            success: function(response) {
                // Update UI if needed
            }
        });
    });

    // Print button
    $('.btn-print').on('click', function() {
        window.print();
    });

    // Date range picker initialization
    if ($.fn.daterangepicker) {
        $('.date-range-picker').daterangepicker({
            opens: 'left',
            locale: {
                format: 'YYYY-MM-DD'
            }
        });
    }

    // Single date picker initialization
    if ($.fn.datepicker) {
        $('.date-picker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }

    // Initialize Select2 for enhanced select boxes
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%'
        });
    }

    // Dashboard charts initialization
    if (typeof Chart !== 'undefined') {
        // Cases by status chart
        if ($('#casesByStatusChart').length) {
            const caseCtx = document.getElementById('casesByStatusChart').getContext('2d');
            const caseData = JSON.parse($('#casesByStatusChart').attr('data-chart'));
            
            new Chart(caseCtx, {
                type: 'doughnut',
                data: {
                    labels: caseData.labels,
                    datasets: [{
                        data: caseData.data,
                        backgroundColor: [
                            '#0d6efd', // Primary
                            '#ffc107', // Warning
                            '#198754', // Success
                            '#0dcaf0', // Info
                            '#6c757d'  // Secondary
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Hearings by month chart
        if ($('#hearingsByMonthChart').length) {
            const hearingCtx = document.getElementById('hearingsByMonthChart').getContext('2d');
            const hearingData = JSON.parse($('#hearingsByMonthChart').attr('data-chart'));
            
            new Chart(hearingCtx, {
                type: 'bar',
                data: {
                    labels: hearingData.labels,
                    datasets: [{
                        label: 'Hearings',
                        data: hearingData.data,
                        backgroundColor: '#0d6efd',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
    }
});
