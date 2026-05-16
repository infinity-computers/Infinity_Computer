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
