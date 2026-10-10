// This file contains the JavaScript for the application.

import './bootstrap';

// Example of a function to handle form submissions for adding data entry personnel
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('data-entry-form');
    if (form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Handle success (e.g., show a success message, reset the form)
                    alert('Data entry personnel added successfully!');
                    form.reset();
                } else {
                    // Handle errors (e.g., show error messages)
                    alert('Error adding data entry personnel: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    }

    // Example of a function to export data to Excel
    const exportButton = document.getElementById('export-button');
    if (exportButton) {
        exportButton.addEventListener('click', () => {
            window.location.href = '/export-data'; // Adjust the URL as needed
        });
    }
});