function getStatusBadgeClass(status) {
    switch(status.toLowerCase()) {
        case 'pending': 
        case 'waiting for parts':
            return 'badge pending';
        case 'diagnosing': 
        case 'repair in progress':
            return 'badge diagnosing';
        case 'completed': 
        case 'ready for pickup': 
        case 'delivered': 
            return 'badge completed';
        case 'cancelled': 
            return 'badge cancelled';
        default: 
            return 'badge default';
    }
}

function formatDate(dateStr) {
    if(!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return d.toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute:'2-digit'
    });
}

window.allEngineers = [];

async function loadEngineersIntoSelect(selectId, defaultValue = '') {
    const select = document.getElementById(selectId);
    if (!select) return;

    try {
        if (window.allEngineers.length === 0) {
            const res = await fetch('api/get_engineers.php');
            const json = await res.json();
            if (json.status === 'success') {
                window.allEngineers = json.data;
            }
        }
        
        const currentVal = select.value || defaultValue;
        select.innerHTML = '<option value="">Select Engineer...</option>';
        window.allEngineers.forEach(eng => {
            const opt = document.createElement('option');
            opt.value = eng.name;
            opt.textContent = eng.name;
            if (eng.name === currentVal) opt.selected = true;
            select.appendChild(opt);
        });
    } catch (e) {
        console.error('Failed to load engineers:', e);
    }
}
