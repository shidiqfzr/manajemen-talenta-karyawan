import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#F3FAF6',
                    100: '#E7F5EC',
                    200: '#C9E8D5',
                    300: '#9BD1B0',
                    400: '#66B586',
                    500: '#3E9A63',
                    600: '#2F8250',
                    700: '#256B3E',
                    800: '#1B5330',
                    900: '#123A22',
                    950: '#0B2416',
                },
                earth: {
                    100: '#F2ECDD',
                    300: '#D8C9A8',
                    500: '#A8916A',
                    600: '#8A7550',
                    700: '#6B5B3A',
                },
                surface: {
                    50: '#F8F9F6',
                    100: '#F1F3EE',
                    200: '#DEE3D8',
                    300: '#C3C9BE',
                    500: '#737A70',
                    700: '#40473F',
                    900: '#1B1F1D',
                },
                danger: '#C0392B',
                warning: '#B8860B',
                info: '#2E6E9E',
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [],
};
