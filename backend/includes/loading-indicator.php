<style>
    #page-loading-indicator {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: grid;
        place-items: center;
        background: rgba(248, 250, 252, 0.78);
        opacity: 0;
        visibility: hidden;
        transition: opacity 160ms ease, visibility 160ms ease;
    }

    #page-loading-indicator.is-visible {
        opacity: 1;
        visibility: visible;
    }

    .page-loading-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fff;
        color: #334155;
        font: 600 14px/1.4 "Noto Sans Thai", sans-serif;
        box-shadow: 0 12px 36px rgba(15, 23, 42, 0.12);
    }

    .page-loading-spinner {
        width: 26px;
        height: 26px;
        border: 3px solid #dbeafe;
        border-top-color: #0758b4;
        border-radius: 50%;
        animation: page-loading-spin 750ms linear infinite;
    }

    @keyframes page-loading-spin {
        to { transform: rotate(360deg); }
    }

    @media (prefers-reduced-motion: reduce) {
        .page-loading-spinner { animation-duration: 1.5s; }
    }
</style>
<div id="page-loading-indicator" role="status" aria-live="polite" aria-hidden="true">
    <div class="page-loading-card">
        <span class="page-loading-spinner" aria-hidden="true"></span>
        <span>กำลังโหลด...</span>
    </div>
</div>
<script>
    (() => {
        const indicator = document.getElementById('page-loading-indicator');
        const show = () => {
            indicator.classList.add('is-visible');
            indicator.setAttribute('aria-hidden', 'false');
        };
        const hide = () => {
            indicator.classList.remove('is-visible');
            indicator.setAttribute('aria-hidden', 'true');
        };

        window.addEventListener('beforeunload', show);
        window.addEventListener('pageshow', hide);
    })();
</script>
