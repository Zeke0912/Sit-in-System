/**
 * Direct PC Selection for Sit-In Monitoring System
 * Enhanced version with better UI/UX for PC selection including maintenance status
 */

// Function to load available PCs for a selected lab
function loadPCSelection(subjectId) {
    if (!subjectId) {
        document.getElementById('pc-selection-container').innerHTML = '<p class="text-muted">Please select a laboratory first.</p>';
        return;
    }

    // Show loading spinner
    document.getElementById('pc-selection-container').innerHTML = 
        '<div class="loading-spinner text-center p-4">' +
        '<i class="fas fa-spinner fa-spin fa-2x"></i>' +
        '<p class="mt-2">Loading PC status...</p>' +
        '</div>';

    // Clear the previously selected PC number
    document.getElementById('pc_number').value = '';

    // Create form data for the request
    const formData = new FormData();
    formData.append('subjectId', subjectId);

    // Fetch PC data from the server
    fetch('get_available_pcs_direct.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            document.getElementById('pc-selection-container').innerHTML = 
                '<div class="alert alert-danger">' + (data.message || 'Error loading PCs') + '</div>';
            return;
        }

        renderPCGrid(
            data.lab_number,
            data.total_pcs,
            data.available_pcs,
            data.occupied_pcs || [],
            data.reserved_pcs || [],
            data.maintenance_pcs || []
        );
    })
    .catch(error => {
        console.error('Error fetching PC data:', error);
        document.getElementById('pc-selection-container').innerHTML = 
            '<div class="alert alert-danger">Failed to load PC data. Please try again later.</div>';
    });
}

// Render the PC selection grid with different status indicators
function renderPCGrid(labNumber, totalPCs, availablePCs, occupiedPCs, reservedPCs, maintenancePCs) {
    const container = document.getElementById('pc-selection-container');
    
    // Clear selected PC when changing labs
    let selectedPC = null;
    document.getElementById('pc_number').value = '';

    // Build the PC grid HTML
    let html = `
        <div class="pc-selection-header">
            <h5 class="mb-3">Select a PC in Laboratory ${labNumber}</h5>
            <div class="pc-legend mb-3">
                <span class="legend-item"><span class="status-dot available"></span> Available</span>
                <span class="legend-item"><span class="status-dot occupied"></span> Occupied</span>
                <span class="legend-item"><span class="status-dot maintenance"></span> Under Maintenance</span>
                <span class="legend-item"><span class="status-dot reserved"></span> Reserved</span>
                <span class="legend-item"><span class="status-dot selected"></span> Selected</span>
            </div>
        </div>
    `;

    // Show warning if maintenance PCs exist
    if (maintenancePCs.length > 0) {
        html += `
            <div class="maintenance-warning mb-3 p-2 rounded" style="background-color: #f8d7da; border-left: 4px solid #9b59b6;">
                <p class="mb-0"><i class="fas fa-exclamation-triangle"></i> PCs marked in purple are under maintenance and cannot be reserved.</p>
            </div>
        `;
    }

    // Start PC grid
    html += '<div class="pc-grid">';

    // Generate PC items
    for (let i = 1; i <= totalPCs; i++) {
        // Determine PC status
        let pcStatus = 'available';
        let disabled = '';

        // Check if PC is under maintenance (highest priority)
        if (maintenancePCs.includes(i)) {
            pcStatus = 'maintenance';
            disabled = 'disabled';
        }
        // Check if PC is reserved
        else if (reservedPCs.includes(i)) {
            pcStatus = 'reserved';
            disabled = 'disabled';
        }
        // Check if PC is occupied
        else if (occupiedPCs.includes(i)) {
            pcStatus = 'occupied';
            disabled = 'disabled';
        }

        // Get appropriate icon based on status
        const icon = getPCStatusIcon(pcStatus);

        // Generate PC item HTML
        html += `
            <div class="pc-item ${pcStatus}" data-pc="${i}" ${disabled} 
                title="PC ${i} - ${pcStatus.charAt(0).toUpperCase() + pcStatus.slice(1)}">
                <i class="fas ${icon}"></i>
                <span class="pc-number">PC ${i}</span>
                ${pcStatus === 'maintenance' ? '<span class="maintenance-label"><i class="fas fa-tools"></i> Maintenance</span>' : ''}
            </div>
        `;
    }

    // Close PC grid
    html += '</div>';

    // Add selection display
    html += `
        <div class="pc-selection-footer mt-3 p-2 rounded text-center" id="pcSelectionFooter">
            <p class="selected-pc-text mb-0">Selected PC: <span id="displaySelectedPC">None</span></p>
        </div>
    `;

    // Set the HTML content
    container.innerHTML = html;

    // Add click handlers to available PCs
    document.querySelectorAll('.pc-item:not([disabled])').forEach(pc => {
        pc.addEventListener('click', function() {
            const pcNumber = parseInt(this.dataset.pc);

            // Add visual click feedback
            this.classList.add('click-animation');
            setTimeout(() => {
                this.classList.remove('click-animation');
            }, 300);

            // Remove selected class from all PCs
            document.querySelectorAll('.pc-item').forEach(item => {
                item.classList.remove('selected');
            });

            // Add selected class to clicked PC
            this.classList.add('selected');

            // Update selected PC value
            selectedPC = pcNumber;
            document.getElementById('pc_number').value = pcNumber;
            document.getElementById('displaySelectedPC').textContent = pcNumber;

            // Update footer style for visual feedback
            document.getElementById('pcSelectionFooter').classList.add('has-selection');

            // Add animation to icon for feedback
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.add('fa-bounce');
                setTimeout(() => {
                    icon.classList.remove('fa-bounce');
                }, 1000);
            }
        });

        // Add hover effects
        pc.addEventListener('mouseenter', function() {
            if (!this.classList.contains('selected')) {
                const icon = this.querySelector('i');
                if (icon) icon.classList.add('fa-beat');
            }
        });

        pc.addEventListener('mouseleave', function() {
            const icon = this.querySelector('i');
            if (icon) icon.classList.remove('fa-beat');
        });
    });
}

// Get appropriate icon for PC status
function getPCStatusIcon(status) {
    switch (status) {
        case 'occupied':
            return 'fa-user';
        case 'maintenance':
            return 'fa-tools';
        case 'reserved':
            return 'fa-clock';
        case 'available':
        default:
            return 'fa-desktop';
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Get the subject select element
    const subjectSelect = document.getElementById('subjectId');
    if (!subjectSelect) return;

    // Add event listener for subject selection
    subjectSelect.addEventListener('change', function() {
        const selectedSubjectId = this.value;
        if (selectedSubjectId) {
            loadPCSelection(selectedSubjectId);
        } else {
            document.getElementById('pc-selection-container').innerHTML = 
                '<p class="text-muted">Please select a laboratory first.</p>';
            document.getElementById('pc_number').value = '';
        }
    });

    // Load PCs if subject is already selected
    if (subjectSelect.value) {
        loadPCSelection(subjectSelect.value);
    }

    // Check for existing selection (on page reload)
    setTimeout(function() {
        const pcNumberInput = document.getElementById('pc_number');
        if (pcNumberInput && pcNumberInput.value) {
            const pcNumber = pcNumberInput.value;
            const pcItem = document.querySelector(`.pc-item[data-pc="${pcNumber}"]`);
            
            if (pcItem) {
                pcItem.classList.add('selected');
                if (document.getElementById('displaySelectedPC')) {
                    document.getElementById('displaySelectedPC').textContent = pcNumber;
                }
                if (document.getElementById('pcSelectionFooter')) {
                    document.getElementById('pcSelectionFooter').classList.add('has-selection');
                }
            }
        }
    }, 500);
}); 