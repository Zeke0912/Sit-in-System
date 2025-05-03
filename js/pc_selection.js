/**
 * PC Selection functionality for Sit-In Monitoring System
 */

// Function to load available PCs for a selected lab
function loadAvailablePCs(subjectId) {
    if (!subjectId) return;
    
    // Show loading message
    document.getElementById('pc-selection-container').innerHTML = '<p>Loading available PCs...</p>';
    
    // Fetch available PCs from the server
    fetch(`get_available_pcs.php?subject_id=${subjectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('pc-selection-container').innerHTML = 
                    `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            // Create PC selection UI
            renderPCSelectionUI(data);
        })
        .catch(error => {
            console.error('Error fetching PC data:', error);
            document.getElementById('pc-selection-container').innerHTML = 
                '<div class="alert alert-danger">Error loading PC data. Please try again.</div>';
        });
}

// Render the PC selection interface
function renderPCSelectionUI(data) {
    const container = document.getElementById('pc-selection-container');
    
    // Create the header
    let html = `
        <div class="pc-selection-header">
            <h3>Select a PC for Lab ${data.lab_number}</h3>
            <p>${data.available_pcs.length} of ${data.total_pcs} PCs available</p>
        </div>
        <div class="pc-grid">
    `;
    
    // Create grid of PCs
    for (let i = 1; i <= data.total_pcs; i++) {
        const isAvailable = data.available_pcs.includes(i);
        const pcClass = isAvailable ? 'pc-available' : 'pc-occupied';
        const disabled = isAvailable ? '' : 'disabled';
        
        html += `
            <div class="pc-item ${pcClass}">
                <input type="radio" name="pc_number" id="pc-${i}" value="${i}" ${disabled}>
                <label for="pc-${i}" class="pc-label">PC ${i}</label>
            </div>
        `;
    }
    
    html += '</div>';
    
    // Render the HTML
    container.innerHTML = html;
    
    // Add event listeners to the radio buttons
    const radioButtons = container.querySelectorAll('input[type="radio"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            // Update the hidden input value
            document.getElementById('selected_pc_number').value = this.value;
            
            // Remove the selected class from all labels
            document.querySelectorAll('.pc-label').forEach(label => {
                label.classList.remove('selected');
            });
            
            // Add the selected class to the chosen PC
            this.nextElementSibling.classList.add('selected');
        });
    });
}

// Initialize PC selection when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Get the subject selection dropdown
    const subjectSelect = document.getElementById('subject_id');
    
    if (subjectSelect) {
        // Add event listener for subject selection change
        subjectSelect.addEventListener('change', function() {
            const selectedSubjectId = this.value;
            if (selectedSubjectId) {
                loadAvailablePCs(selectedSubjectId);
            } else {
                document.getElementById('pc-selection-container').innerHTML = '';
            }
        });
        
        // If a subject is already selected on page load, load its PCs
        if (subjectSelect.value) {
            loadAvailablePCs(subjectSelect.value);
        }
    }
}); 