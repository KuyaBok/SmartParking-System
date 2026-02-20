<?php
// Guard scan UI - keyboard-wedge friendly
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Guard Scan</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="assets/js/reload_on_nav.js"></script>
    <style>
        body{background:transparent}
        .guard-card{background:#fff;padding:20px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.06);max-width:720px;margin:20px auto}
        .guard-input{width:100%;padding:14px;font-size:20px;border:1px solid #e3e6ea;border-radius:6px}
        .guard-status{margin-top:14px;padding:12px;border-radius:6px;display:none}
        .guard-status.ok{background:#e6ffed;border:1px solid #9be6b4;color:#0b7937}
        .guard-status.err{background:#ffecec;border:1px solid #f1a9a9;color:#a11}
        .guard-meta{font-size:13px;color:#666;margin-top:10px}
        .scan-header{display:flex;align-items:center;gap:12px;margin-bottom:12px}
        .scan-header h2{margin:0}
        .toast{position:fixed;left:50%;transform:translateX(-50%);top:20px;min-width:260px;padding:14px 18px;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,0.12);color:#fff;font-weight:600;display:none;z-index:9999}
        .toast.show{display:block}
        .toast.in{background:#16a34a}
        .toast.out{background:#dc2626}
        .toast .sub{display:block;font-weight:400;font-size:13px;opacity:0.95}
    </style>
</head>
<body>

    <a href="dashboard.php" class="back-btn" style="margin:18px;display:inline-block">
        <svg viewBox="0 0 24 24" width="18" height="18"><path d="M15.5 19l-7-7 7-7"/></svg>
        Back
    </a>

    <main class="main-content" style="padding: 0 18px 60px;">
        <div id="guardCard" class="guard-card">
            <div class="scan-header">
                <h2>Guard Scanner</h2>
                <div style="color:#666;font-size:14px;">Keep this page active and focused when scanning</div>
            </div>

            <form id="scanForm">
                <input id="codeInput" class="guard-input" name="code" autocomplete="off" autofocus placeholder="Scan QR here" />
            </form>

            <div id="status" class="guard-status"></div>
            <div class="guard-meta">Tip: use a 2D/QR scanner in keyboard-wedge mode. It types the payload into the input and sends Enter.</div>
        </div>
    </main>

    <div id="toast" class="toast" role="status" aria-live="polite"><span id="toastMain"></span><span id="toastSub" class="sub"></span></div>

    <script>
        const form = document.getElementById('scanForm');
        const input = document.getElementById('codeInput');
        const status = document.getElementById('status');

        async function submitCode(code){
            status.style.display = '';
            status.className = 'guard-status';
            status.textContent = 'Processing...';
            try {
                const fd = new FormData(); fd.append('code', code);
                const res = await fetch('scan_handler.php', { method: 'POST', body: fd });
                const j = await res.json();
                if (j.success) {
                    status.classList.add('ok');
                    status.textContent = (j.action ? j.action + ' — ' : '') + (j.message || 'OK');
                    showToast(j.action || 'OK', j.message || '', j.action === 'IN' ? 'in' : 'out');
                } else {
                    status.classList.add('err');
                    status.textContent = j.message || 'Error';
                    showToast('Error', j.message || 'Scan failed', 'out');
                }
            } catch (err) {
                status.classList.add('err');
                status.textContent = 'Network/server error';
                showToast('Error','Network/server error','out');
            }
            setTimeout(() => { input.value = ''; input.focus(); }, 700);
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const code = input.value.trim();
            if (!code) return;
            submitCode(code);
        });

        input.addEventListener('keypress', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = input.value.trim();
                if (code) submitCode(code);
            }
        });

        const toast = document.getElementById('toast');
        const toastMain = document.getElementById('toastMain');
        const toastSub = document.getElementById('toastSub');
        const guardCard = document.getElementById('guardCard');
        let toastTimer = null;
        function positionToast(){
            try{ if (!guardCard) return; const rect = guardCard.getBoundingClientRect(); const top = rect.bottom + 12 + window.scrollY; const centerX = rect.left + (rect.width / 2) + window.scrollX; toast.style.top = top + 'px'; toast.style.left = centerX + 'px'; toast.style.transform = 'translateX(-50%)'; } catch(e){}
        }
        function showToast(title, sub, type){
            clearTimeout(toastTimer);
            toast.className = 'toast ' + (type === 'in' ? 'in' : 'out') + ' show';
            toastMain.textContent = title;
            toastSub.textContent = sub || '';
            toast.style.display = 'block';
            positionToast();
            toastTimer = setTimeout(()=>{ toast.style.display='none'; toast.className='toast'; }, 2500);
        }
    </script>
</body>
</html>
