/**
 * PC Selection functionality for Sit-In Monitoring System (Updated version)
 */

// Function to load available PCs for a selected lab
function loadAvailablePCs(subjectId) {
    if (!subjectId) return;
    
    // Show loading message
    document.getElementById('pc-selection-container').innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading available PCs...</div>';
    
    // Clear the previously selected PC number when changing labs
    document.getElementById('selected_pc_number').value = '';
    
    // Fetch available PCs from the updated API endpoint
    fetch('get_available_pcs_updated.php?subject_id=' + subjectId)
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            document.getElementById('pc-selection-container').innerHTML = `<p class="error-text">${data.error || 'Error loading PCs. Please try again.'}</p>`;
        } else {
            console.log('Maintenance PCs from server:', data.maintenance_pcs);
            renderPCSelection(data.lab_number, data.available_pcs, data.total_pcs, data.occupied_pcs, data.reserved_pcs, data.maintenance_pcs);
        }
    })
    .catch(error => {
        console.error('Error fetching PCs:', error);
        document.getElementById('pc-selection-container').innerHTML = '<p class="error-text">Failed to load PC data. Please try again later.</p>';
    });
}

// Render the PC selection interface
function renderPCSelection(labNumber, availablePCs, totalPCs, occupiedPCs, reservedPCs, maintenancePCs) {
    const container = document.getElementById('pc-selection-container');
    
    // Clear selected PC when changing labs
    let selectedPc = null;
    document.getElementById('selected_pc_number').value = '';
    
    // Ensure arrays are arrays (not null)
    occupiedPCs = occupiedPCs || [];
    reservedPCs = reservedPCs || [];
    maintenancePCs = maintenancePCs || [];
    
    console.log('PC status counts:', {
        total: totalPCs,
        available: availablePCs ? availablePCs.length : 0,
        occupied: occupiedPCs.length,
        reserved: reservedPCs.length,
        maintenance: maintenancePCs.length
    });
    
    // Create PC selection HTML
    let html = `
        <div class="pc-selection-header">
            <h4>Select a PC in Laboratory ${labNumber}</h4>
            <div class="pc-legend">
                <span class="legend-item"><span class="status-dot available"></span> Available</span>
                <span class="legend-item"><span class="status-dot occupied"></span> Occupied</span>
                <span class="legend-item"><span class="status-dot maintenance"></span> Under Maintenance</span>
                <span class="legend-item"><span class="status-dot reserved"></span> Reserved</span>
                <span class="legend-item"><span class="status-dot selected"></span> Selected</span>
            </div>
        </div>
        <div class="maintenance-warning" style="display: ${maintenancePCs.length > 0 ? 'block' : 'none'}; margin-bottom: 15px; padding: 10px; background-color: #f8d7da; border-left: 4px solid #9b59b6; color: #721c24; border-radius: 4px;">
            <strong><i class="fas fa-exclamation-triangle"></i> Notice:</strong> PCs marked in purple are under maintenance and cannot be reserved.
        </div>
        <div class="pc-grid">
    `;
    
    // Generate PC grid
    for (let i = 1; i <= totalPCs; i++) {
        // Determine PC status
        let pcStatus = 'available'; // Default status
        let disabled = '';
        
        // Convert to numbers for safer comparisons
        const pc = Number(i);
        
        // Check if in maintenancePCs (prioritize maintenance status)
        if (maintenancePCs && maintenancePCs.some(num => Number(num) === pc)) {
            pcStatus = 'maintenance';
            disabled = 'disabled';
            console.log(`PC ${i} is marked as maintenance`);
        } 
        // Check if in reservedPCs
        else if (reservedPCs && reservedPCs.some(num => Number(num) === pc)) {
            pcStatus = 'reserved';
            disabled = 'disabled';
        } 
        // Check if in occupiedPCs
        else if (occupiedPCs && occupiedPCs.some(num => Number(num) === pc)) {
            pcStatus = 'occupied';
            disabled = 'disabled';
        }
        
        html += `
            <div class="pc-item ${pcStatus} ${selectedPc === i ? 'selected' : ''}" data-pc="${i}" ${disabled} title="PC ${i} - ${pcStatus.charAt(0).toUpperCase() + pcStatus.slice(1)}">
                <i class="fas ${getPCIcon(pcStatus)}"></i>
                <span class="pc-number">PC ${i}</span>
                ${pcStatus === 'maintenance' ? '<span class="maintenance-label"><i class="fas fa-tools"></i> Maintenance</span>' : ''}
            </div>
        `;
    }
    
    html += `
        </div>
        <div class="pc-selection-footer" id="pcSelectionFooter">
            <p class="selected-pc-text">Selected PC: <span id="displaySelectedPC">${selectedPc || 'None'}</span></p>
        </div>
    `;
    
    // Set the HTML
    container.innerHTML = html;
    
    // Add click handlers to PC items
    document.querySelectorAll('.pc-item:not([disabled])').forEach(pc => {
        pc.addEventListener('click', function() {
            const pcNumber = parseInt(this.dataset.pc);
            
            // Add visual feedback for click
            this.classList.add('click-animation');
            setTimeout(() => {
                this.classList.remove('click-animation');
            }, 300);
            
            // Remove selected class from all PCs
            document.querySelectorAll('.pc-item').forEach(item => item.classList.remove('selected'));
            
            // Add selected class to this PC
            this.classList.add('selected');
            
            // Update selected PC
            selectedPc = pcNumber;
            document.getElementById('selected_pc_number').value = pcNumber;
            
            // Update display text
            document.getElementById('displaySelectedPC').textContent = pcNumber;
            
            // Add 'has-selection' class to the footer for visual feedback
            document.getElementById('pcSelectionFooter').classList.add('has-selection');
            
            // Add feedback animation
            const selectedIcon = this.querySelector('i');
            if (selectedIcon) {
                selectedIcon.classList.add('fa-bounce');
                setTimeout(() => {
                    selectedIcon.classList.remove('fa-bounce');
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
    
    // Add non-interactive tooltip effect for disabled items
    document.querySelectorAll('.pc-item[disabled]').forEach(pc => {
        const status = pc.classList.contains('maintenance') ? 'Under Maintenance' : 
                      pc.classList.contains('occupied') ? 'Currently Occupied' : 'Reserved';
        
        pc.setAttribute('title', `PC ${pc.dataset.pc} - ${status} (Cannot Select)`);
    });
}

// Helper function to get appropriate icon for PC status
function getPCIcon(status) {
    switch(status) {
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

// Initialize PC selection when the page loads
document.addEventListener('DOMContentLoaded', function() {
    const subjectSelect = document.getElementById('subject_id');
    const pcSelectionContainer = document.getElementById('pc-selection-container');
    const selectedPcInput = document.getElementById('selected_pc_number');
    
    // Listen for laboratory selection change
    if (subjectSelect) {
        subjectSelect.addEventListener('change', function() {
            const selectedSubjectId = this.value;
            
            if (selectedSubjectId) {
                loadAvailablePCs(selectedSubjectId);
            } else {
                pcSelectionContainer.innerHTML = '<p>Please select a laboratory first to view available PCs.</p>';
                selectedPcInput.value = '';
            }
        });
    }
    
    // Auto-load PCs if a lab is already selected
    if (subjectSelect && subjectSelect.value) {
        loadAvailablePCs(subjectSelect.value);
    }
    
    // Check if a PC is already selected (page reload case)
    setTimeout(function() {
        if (selectedPcInput && selectedPcInput.value) {
            const pcNumber = selectedPcInput.value;
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