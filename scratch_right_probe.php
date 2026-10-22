<?php
$data = json_decode(file_get_contents('scratch_audit_results.json'), true);
?>
<!DOCTYPE html>
<html>
<head><title>Right Side Probe</title></head>
<body>
<script>
function log(msg) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'scratch_logger.php', false);
    xhr.send(msg);
}

async function run() {
    const pages = ['customer_dashboard.php', 'customer_reservation.php', 'customer_history.php', 'customer_profile.php'];
    const res = {};
    for (const page of pages) {
        const frame = document.createElement('iframe');
        frame.style.width = '1280px';
        frame.style.height = '800px';
        frame.src = page;
        document.body.appendChild(frame);
        await new Promise(r => frame.onload = r);
        await new Promise(r => setTimeout(r, 600));
        
        const doc = frame.contentDocument;
        const win = frame.contentWindow;
        
        const right = doc.querySelector('.customer-navbar-right');
        const acctWrapper = doc.querySelector('.customer-account-wrapper');
        const acctLink = doc.querySelector('.customer-account-link');
        const details = doc.querySelector('.customer-account-details');
        const name = doc.querySelector('.customer-top-name');
        const dt = doc.querySelector('.customer-date-time');
        const date = doc.querySelector('.customer-date');
        const time = doc.querySelector('.customer-time');
        const toggle = doc.querySelector('.customer-account-toggle');
        
        const cs = el => el ? win.getComputedStyle(el) : {};
        const rect = el => {
            if (!el) return null;
            const r = el.getBoundingClientRect();
            return { top: Math.round(r.top), height: Math.round(r.height), width: Math.round(r.width) };
        };
        
        res[page] = {
            rightRect: rect(right),
            acctWrapperRect: rect(acctWrapper),
            acctLinkRect: rect(acctLink),
            detailsRect: rect(details),
            nameRect: rect(name),
            dtRect: rect(dt),
            dateRect: rect(date),
            timeRect: rect(time),
            toggleRect: rect(toggle),
            
            // Line heights
            nameLH: cs(name).lineHeight,
            dateLH: cs(date).lineHeight,
            timeLH: cs(time).lineHeight,
            dtMarginTop: cs(dt).marginTop,
            dtGap: cs(dt).gap,
            detailsAlign: cs(details).alignItems,
            acctLinkAlign: cs(acctLink).alignItems
        };
        document.body.removeChild(frame);
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'scratch_save_right_probe.php', false);
    xhr.send(JSON.stringify(res, null, 2));
    log('Right probe finished');
}
run();
</script>
</body>
</html>
