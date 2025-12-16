/**
 * Système de filtres automatiques global
 * Applique automatiquement les filtres sans bouton "Filtrer"
 */

// Fonction pour appliquer les filtres automatiquement
function applyAutoFilters() {
    const url = new URL(window.location.href);
    const params = new URLSearchParams();
    
    // Liste des filtres possibles avec leurs noms de paramètres
    const filterMappings = {
        'annee_filter': 'annee_id',
        'filiere_filter': 'filiere_id',
        'niveau_filter': 'niveau_id',
        'classe_filter': 'classe_id',
        'matiere_filter': 'matiere_id',
        'semestre_filter': 'semestre',
        'trimestre_filter': 'trimestre',
        'type_filter': 'type',
        'statut_filter': 'statut',
        'role_filter': 'role',
        'mois_filter': 'mois',
        'periode_filter': 'periode',
        'etudiant_filter': 'etudiant_id',
        'enseignant_filter': 'enseignant_id',
        'evaluation_filter': 'evaluation_id'
    };
    
    // Récupérer les valeurs de tous les filtres présents sur la page
    Object.keys(filterMappings).forEach(filterId => {
        const filterElement = document.getElementById(filterId);
        if (filterElement && filterElement.value) {
            params.append(filterMappings[filterId], filterElement.value);
        }
    });
    
    // Récupérer la valeur de recherche si présente
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput && searchInput.value) {
        params.append('search', searchInput.value);
    }
    
    // Rediriger avec les nouveaux paramètres
    const newUrl = url.pathname + (params.toString() ? '?' + params.toString() : '');
    window.location.href = newUrl;
}

// Fonction pour gérer la recherche automatique avec debounce
let searchTimeout;
function handleSearchInput(event) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyAutoFilters();
    }, 800); // Attendre 800ms après la dernière frappe
}

// Fonction pour initialiser les filtres automatiques
function initAutoFilters() {
    const filterIds = [
        'annee_filter',
        'filiere_filter',
        'niveau_filter',
        'classe_filter',
        'matiere_filter',
        'semestre_filter',
        'trimestre_filter',
        'type_filter',
        'statut_filter',
        'role_filter',
        'mois_filter',
        'periode_filter',
        'etudiant_filter',
        'enseignant_filter',
        'evaluation_filter'
    ];
    
    // Attacher l'événement onchange à tous les filtres
    filterIds.forEach(filterId => {
        const filterElement = document.getElementById(filterId);
        if (filterElement) {
            filterElement.addEventListener('change', applyAutoFilters);
        }
    });
    
    // Attacher l'événement de recherche automatique
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearchInput);
    }
}

// Initialiser les filtres au chargement de la page
document.addEventListener('DOMContentLoaded', initAutoFilters);

// Fonction pour l'horloge en temps réel (utilisée dans plusieurs pages)
function updateTime() {
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        timeElement.textContent = now.toLocaleDateString('fr-FR', options);
    }
}

// Démarrer l'horloge si l'élément existe
if (document.getElementById('currentTime')) {
    updateTime();
    setInterval(updateTime, 1000);
}
