<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-database-compat.js"></script>
<script>
(function() {
    const cfg = {
        apiKey: @json(config('firebase.web.api_key')),
        authDomain: @json(config('firebase.web.auth_domain')),
        databaseURL: @json(config('firebase.web.database_url')),
        projectId: @json(config('firebase.web.project_id')),
        storageBucket: @json(config('firebase.web.storage_bucket')),
        messagingSenderId: @json(config('firebase.web.messaging_sender_id')),
        appId: @json(config('firebase.web.app_id')),
    };
    if (!cfg.apiKey || !cfg.databaseURL) {
        console.warn('[Firebase RTDB] Web config missing, listeners disabled.');
        window.RTDB_DISABLED = true;
        return;
    }
    try {
        if (!firebase.apps || !firebase.apps.length) {
            firebase.initializeApp(cfg);
        }
        window.firebaseDB = firebase.database();
        window.RTDB_READY = true;
        console.info('[Firebase RTDB] Real-time listeners ready.');
    } catch (e) {
        console.error('[Firebase RTDB] Init failed:', e);
        window.RTDB_DISABLED = true;
    }
})();
</script>
