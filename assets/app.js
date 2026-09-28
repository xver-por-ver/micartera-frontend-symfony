/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

const storedTheme = localStorage.getItem('theme') || 'dark';
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
var navMenuIsOpen = false;
function navMenuShow(event) {
    navMenuOpen.classList.toggle("hide");
    navMenuClose.classList.toggle("hide");
    navMenu.classList.toggle("hide");
    navMenuBg.classList.toggle("hide");
    navMenuIsOpen = !navMenuIsOpen;
}
if (navMenuOpen != null) {
    navMenuOpen.addEventListener("click", (event) => { navMenuShow(event); });
    navMenuClose.addEventListener("click", (event) => { navMenuShow(event); });
    navMenuBg.addEventListener("mouseover", (event) => { navMenuShow(event); });
}
const beforeUnloadHandler = (event) => {
    if (navMenuIsOpen) {
        navMenuShow(event);
    }
};
window.addEventListener("beforeunload", beforeUnloadHandler);

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';
