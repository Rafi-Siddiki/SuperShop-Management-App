import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Models/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Supershop red, taken from the original #FF0000 → #FF8938 brand gradient.
                brand: {
                    50: '#fff1f1',
                    100: '#ffe1e1',
                    200: '#ffc7c7',
                    300: '#ffa0a0',
                    400: '#ff6a6a',
                    500: '#f83b3b',
                    600: '#e51d1d',
                    700: '#c11414',
                    800: '#a01414',
                    900: '#841818',
                    950: '#480707',
                },
                accent: {
                    400: '#ffa463',
                    500: '#ff8938',
                    600: '#f06a12',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                lift: '0 10px 30px -12px rgb(15 23 42 / 0.25)',
            },
        },
    },

    plugins: [forms],
};
