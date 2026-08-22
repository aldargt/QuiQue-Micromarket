import './bootstrap';
import './product-barcode-scanner';

import Alpine from 'alpinejs';

const applyTheme = (theme) => {
    const dark = theme === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    document.querySelectorAll('[data-theme-label]').forEach((label) => {
        label.textContent = dark ? 'Modo claro' : 'Modo oscuro';
    });
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-label', dark ? 'Activar modo claro' : 'Activar modo oscuro');
        button.setAttribute('title', dark ? 'Activar modo claro' : 'Activar modo oscuro');
    });
};

window.toggleTheme = () => {
    const theme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('quique-theme', theme);
    applyTheme(theme);
};

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(localStorage.getItem('quique-theme') === 'dark' ? 'dark' : 'light');
});

window.Alpine = Alpine;

Alpine.start();
