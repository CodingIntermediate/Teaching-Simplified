// Main JavaScript for University Platform

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});

// Confirm delete function
function confirmDelete(url) {
    if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
        window.location.href = url;
    }
    return false;
}

// File upload preview
function previewFile(input, previewId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Form validation
function validateForm(formId) {
    var form = document.getElementById(formId);
    var inputs = form.querySelectorAll('[required]');
    var isValid = true;
    
    inputs.forEach(function(input) {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// AJAX function
function ajaxRequest(url, method, data, successCallback, errorCallback) {
    var xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            if (successCallback) successCallback(JSON.parse(xhr.responseText));
        } else {
            if (errorCallback) errorCallback(xhr.statusText);
        }
    };
    
    xhr.onerror = function() {
        if (errorCallback) errorCallback('Network error');
    };
    
    var encodedData = '';
    if (data) {
        for (var key in data) {
            if (encodedData) encodedData += '&';
            encodedData += encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
        }
    }
    
    xhr.send(encodedData);
}

// Load more resources
function loadMoreResources(topic, containerId) {
    var container = document.getElementById(containerId);
    var button = event.target;
    
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    
    ajaxRequest(
        window.location.href,
        'GET',
        { ajax: 1, topic: topic },
        function(response) {
            var html = '<div class="mt-3"><h6>Additional Resources:</h6><ul>';
            response.forEach(function(resource) {
                html += '<li><a href="' + resource.url + '" target="_blank">' + resource.title + '</a><br>' +
                        '<small class="text-muted">' + resource.description + '</small></li>';
            });
            html += '</ul></div>';
            container.innerHTML = html;
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-sync-alt"></i> Find More Resources';
        },
        function(error) {
            container.innerHTML = '<div class="alert alert-danger">Error loading resources: ' + error + '</div>';
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-sync-alt"></i> Try Again';
        }
    );
}

// Track resource clicks
function trackResource(resourceType, resourceId, externalUrl) {
    ajaxRequest(
        'track_resource.php',
        'POST',
        {
            resource_type: resourceType,
            resource_id: resourceId,
            external_url: externalUrl
        },
        function(response) {
            console.log('Resource tracked:', response);
        },
        function(error) {
            console.error('Error tracking resource:', error);
        }
    );
}

// Quiz timer
function startQuizTimer(duration, displayId) {
    var timer = duration, minutes, seconds;
    var interval = setInterval(function() {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);
        
        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;
        
        document.getElementById(displayId).innerHTML = minutes + ":" + seconds;
        
        if (--timer < 0) {
            clearInterval(interval);
            document.getElementById('quiz-form').submit();
        }
    }, 1000);
}

// Save quiz progress locally
function saveQuizProgress() {
    var formData = new FormData(document.getElementById('quiz-form'));
    var progress = {};
    
    for (var pair of formData.entries()) {
        progress[pair[0]] = pair[1];
    }
    
    localStorage.setItem('quiz_progress', JSON.stringify(progress));
}

// Load saved quiz progress
function loadQuizProgress() {
    var saved = localStorage.getItem('quiz_progress');
    if (saved) {
        var progress = JSON.parse(saved);
        for (var key in progress) {
            var element = document.querySelector('[name="' + key + '"]');
            if (element) {
                if (element.type === 'radio' || element.type === 'checkbox') {
                    if (element.value === progress[key]) {
                        element.checked = true;
                    }
                } else {
                    element.value = progress[key];
                }
            }
        }
        
        if (confirm('Saved progress found. Do you want to continue where you left off?')) {
            localStorage.removeItem('quiz_progress');
        }
    }
}

// Chart initialization
function initSubjectChart(canvasId, labels, data) {
    var ctx = document.getElementById(canvasId).getContext('2d');
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Students Enrolled',
                data: data,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Search functionality
function liveSearch(searchInput, tableId) {
    var input = document.getElementById(searchInput);
    var filter = input.value.toUpperCase();
    var table = document.getElementById(tableId);
    var rows = table.getElementsByTagName('tr');
    
    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        var found = false;
        
        for (var j = 0; j < cells.length; j++) {
            var cell = cells[j];
            if (cell) {
                var textValue = cell.textContent || cell.innerText;
                if (textValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        rows[i].style.display = found ? '' : 'none';
    }
}

// Export results as CSV
function exportToCSV(tableId, filename) {
    var table = document.getElementById(tableId);
    var rows = table.querySelectorAll('tr');
    var csv = [];
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (var j = 0; j < cols.length; j++) {
            row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
        }
        
        csv.push(row.join(','));
    }
    
    var csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    var downloadLink = document.createElement('a');
    downloadLink.download = filename + '.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
}

// Print function
function printPage() {
    window.print();
}

// Dark mode toggle
function toggleDarkMode() {
    var element = document.body;
    element.classList.toggle('dark-mode');
    
    var isDarkMode = element.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDarkMode);
}

// Load dark mode preference
function loadDarkModePreference() {
    var isDarkMode = localStorage.getItem('darkMode') === 'true';
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDarkModePreference();
    
    // Add dark mode toggle button if not exists
    if (!document.getElementById('darkModeToggle')) {
        var toggleBtn = document.createElement('button');
        toggleBtn.id = 'darkModeToggle';
        toggleBtn.className = 'btn btn-sm btn-secondary position-fixed';
        toggleBtn.style.bottom = '20px';
        toggleBtn.style.right = '20px';
        toggleBtn.style.zIndex = '1000';
        toggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
        toggleBtn.onclick = toggleDarkMode;
        document.body.appendChild(toggleBtn);
    }
});