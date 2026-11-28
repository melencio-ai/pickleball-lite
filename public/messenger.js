(() => {
    const media=matchMedia('(max-width: 850px)');
    const mobileDevice=/Android|iPhone|iPad|iPod|Mobile|Windows Phone/i.test(navigator.userAgent) ||
        navigator.userAgentData?.mobile === true || (navigator.platform==='MacIntel' && navigator.maxTouchPoints>1);
    const selector='a[data-messenger-mobile][data-messenger-desktop]';
    function update(link){link.href=mobileDevice || media.matches ? link.dataset.messengerMobile : link.dataset.messengerDesktop;}
    function scan(root){if(root.matches?.(selector))update(root);root.querySelectorAll?.(selector).forEach(update);}
    scan(document);
    media.addEventListener('change',()=>scan(document));
    // Calendar fragments and dialogs load asynchronously.
    new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(scan))).observe(document.body,{childList:true,subtree:true});
})();
