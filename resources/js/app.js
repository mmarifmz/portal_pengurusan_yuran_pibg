import '../../vendor/masmerise/livewire-toaster/resources/js';

const getPortalSchoolLogo = () =>
    document
        .querySelector('meta[name="portal-school-logo"]')
        ?.getAttribute('content')
        ?.trim();

const syncPwaInstallIcon = () => {
    const logoUrl = getPortalSchoolLogo();
    if (!logoUrl) return;

    const installIcon = document.querySelector('#install-button img');
    if (!installIcon) return;

    if (installIcon.getAttribute('src') !== logoUrl) {
        installIcon.setAttribute('src', logoUrl);
    }
};

syncPwaInstallIcon();

window.addEventListener('DOMContentLoaded', syncPwaInstallIcon);
window.addEventListener('load', syncPwaInstallIcon);
window.addEventListener('livewire:navigated', syncPwaInstallIcon);

const pwaInstallObserver = new MutationObserver(syncPwaInstallIcon);
pwaInstallObserver.observe(document.documentElement, {
    childList: true,
    subtree: true,
});
