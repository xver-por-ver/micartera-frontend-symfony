/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

let storedTheme = localStorage.getItem('theme') || 'dark';
document.documentElement.dataset.theme = storedTheme;

const themeSelector = document.querySelector('#themeSelector');
if (themeSelector != null) {
    themeSelector.value = storedTheme;
    themeSelector.addEventListener('change', (event) => {
        const theme = event.target.value;
        localStorage.setItem('theme', theme);
        document.documentElement.dataset.theme = theme;
    });
}

const navMenuOpen = document.querySelector(".navMenuOpen");
const navMenuClose = document.querySelector(".navMenuClose");
const navMenu = document.querySelector(".navMenu");
const navMenuBg = document.querySelector(".navMenuBg");
let navMenuIsOpen = false;
let navMenuCloseTimer;
function setNavMenuOpen(isOpen) {
    navMenuIsOpen = isOpen;
    navMenuOpen?.classList.toggle("hide", isOpen);
    navMenuClose?.classList.toggle("hide", !isOpen);
    navMenu?.classList.toggle("hide", !isOpen);
    navMenuBg?.classList.toggle("hide", !isOpen);
    navMenuOpen?.setAttribute("aria-expanded", String(isOpen));
}
function scheduleNavMenuClose() {
    window.clearTimeout(navMenuCloseTimer);
    navMenuCloseTimer = window.setTimeout(() => setNavMenuOpen(false), 180);
}
if (navMenuOpen != null) {
    const hoverCapable = window.matchMedia("(hover: hover) and (pointer: fine)");
    navMenuOpen.addEventListener("click", () => setNavMenuOpen(!navMenuIsOpen));
    navMenuClose?.addEventListener("click", () => setNavMenuOpen(false));
    navMenuBg?.addEventListener("click", () => setNavMenuOpen(false));
    if (hoverCapable.matches) {
        navMenuOpen.addEventListener("pointerenter", () => {
            window.clearTimeout(navMenuCloseTimer);
            setNavMenuOpen(true);
        });
        [navMenuOpen, navMenuClose, navMenu].forEach((element) => {
            element?.addEventListener("pointerleave", scheduleNavMenuClose);
            element?.addEventListener("pointerenter", () => window.clearTimeout(navMenuCloseTimer));
        });
    }
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && navMenuIsOpen) setNavMenuOpen(false);
    });
}
window.addEventListener("beforeunload", () => {
    if (navMenuIsOpen) setNavMenuOpen(false);
});

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';
