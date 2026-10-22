<?php
require_once 'session_config.php';
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 4;
    $_SESSION['role'] = 'customer';
    $_SESSION['fullname'] = 'John Rennel Masangkay';
    $_SESSION['username'] = 'rennel';
}
?>
<!DOCTYPE html>
<html>
<head><title>Navbar Auditor 2</title></head>
<body>
<h1>Navbar Auditor 2</h1>
<script>
function log(msg) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'scratch_logger.php', false);
    xhr.setRequestHeader('Content-Type', 'text/plain');
    xhr.send(msg);
}

async function run() {
    try {
        const pages = ['customer_dashboard.php', 'customer_reservation.php', 'customer_history.php', 'customer_profile.php'];
        const results = {};
        for (const page of pages) {
            log('Loading ' + page);
            const frame = document.createElement('iframe');
            frame.style.width = '1280px';
            frame.style.height = '800px';
            frame.src = page;
            document.body.appendChild(frame);
            
            await new Promise(r => frame.onload = r);
            await new Promise(r => setTimeout(r, 600));
            
            const doc = frame.contentDocument;
            const win = frame.contentWindow;
            
            // Elements to inspect
            const nav = doc.querySelector('.customer-navbar');
            const logo = doc.querySelector('.customer-navbar-logo');
            const logoImg = doc.querySelector('.customer-navbar-logo img');
            const center = doc.querySelector('.customer-navbar-center');
            const links = Array.from(doc.querySelectorAll('.customer-nav-link'));
            const activeLink = doc.querySelector('.customer-nav-link.active');
            const right = doc.querySelector('.customer-navbar-right');
            const notifWrapper = doc.querySelector('.customer-notification-wrapper');
            const bell = doc.querySelector('.customer-top-notification');
            const bellI = doc.querySelector('.customer-top-notification i');
            const badge = doc.querySelector('.customer-notification-badge');
            const notifDropdown = doc.querySelector('.customer-notification-dropdown');
            const accountWrapper = doc.querySelector('.customer-account-wrapper');
            const avatar = doc.querySelector('.customer-avatar');
            const name = doc.querySelector('.customer-top-name');
            const date = doc.querySelector('.customer-date');
            const time = doc.querySelector('.customer-time');
            const toggle = doc.querySelector('.customer-account-toggle');
            const accountDropdown = doc.querySelector('.customer-account-dropdown');
            
            // Get computed style helper
            const getCS = el => el ? win.getComputedStyle(el) : null;
            
            // Positions relative to viewport (getBoundingClientRect)
            const getRect = el => {
                if (!el) return null;
                const r = el.getBoundingClientRect();
                return { top: Math.round(r.top), left: Math.round(r.left), width: Math.round(r.width), height: Math.round(r.height) };
            };
            
            results[page] = {
                navRect: getRect(nav),
                logoRect: getRect(logo),
                logoImgRect: getRect(logoImg),
                centerRect: getRect(center),
                firstLinkRect: getRect(links[0]),
                secondLinkRect: getRect(links[1]),
                thirdLinkRect: getRect(links[2]),
                fourthLinkRect: getRect(links[3]),
                rightRect: getRect(right),
                bellRect: getRect(bell),
                avatarRect: getRect(avatar),
                nameRect: getRect(name),
                toggleRect: getRect(toggle),
                
                // Style details
                navStyles: {
                    height: getCS(nav)?.height,
                    padding: getCS(nav)?.padding,
                    gap: getCS(nav)?.gap,
                    fontFamily: getCS(nav)?.fontFamily
                },
                centerStyles: {
                    gap: getCS(center)?.gap,
                    marginLeft: getCS(center)?.marginLeft
                },
                linkStyles: {
                    fontFamily: getCS(links[0])?.fontFamily,
                    fontSize: getCS(links[0])?.fontSize,
                    fontWeight: getCS(links[0])?.fontWeight,
                    lineHeight: getCS(links[0])?.lineHeight,
                    color: getCS(links[0])?.color,
                    padding: getCS(links[0])?.padding
                },
                activeLinkStyles: {
                    fontFamily: getCS(activeLink)?.fontFamily,
                    fontSize: getCS(activeLink)?.fontSize,
                    fontWeight: getCS(activeLink)?.fontWeight,
                    color: getCS(activeLink)?.color
                },
                nameStyles: {
                    fontFamily: getCS(name)?.fontFamily,
                    fontSize: getCS(name)?.fontSize,
                    fontWeight: getCS(name)?.fontWeight,
                    lineHeight: getCS(name)?.lineHeight
                },
                dateStyles: {
                    fontSize: getCS(date)?.fontSize,
                    color: getCS(date)?.color
                },
                timeStyles: {
                    fontSize: getCS(time)?.fontSize,
                    color: getCS(time)?.color
                },
                bellStyles: {
                    width: getCS(bell)?.width,
                    height: getCS(bell)?.height,
                    fontSize: getCS(bell)?.fontSize,
                    padding: getCS(bell)?.padding
                },
                bellIconStyles: {
                    fontSize: getCS(bellI)?.fontSize
                },
                accountDropdownStyles: {
                    width: getCS(accountDropdown)?.width,
                    top: getCS(accountDropdown)?.top,
                    borderRadius: getCS(accountDropdown)?.borderRadius
                },
                notifDropdownStyles: {
                    width: getCS(notifDropdown)?.width,
                    top: getCS(notifDropdown)?.top,
                    right: getCS(notifDropdown)?.right,
                    borderRadius: getCS(notifDropdown)?.borderRadius
                }
            };
            
            log('Finished ' + page);
            document.body.removeChild(frame);
        }
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'scratch_save_audit.php', false);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify(results));
        log('Detailed audit finished and saved');
    } catch (e) {
        log('Exception: ' + e.message);
    }
}
run();
</script>
</body>
</html>
