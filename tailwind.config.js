/** @type {import('tailwindcss').Config} */
export default {
    // Tailwind v4 + @tailwindcss/vite: content scanning is driven mainly by @source in resources/css/app.css.
    // Keep this list in sync for tooling/IDE plugins and any legacy pipelines that still read this file.
    content: [
      "./resources/**/*.blade.php",
      "./resources/**/*.js",
      "./resources/**/*.vue",
      "./app/**/*.php",
      "./app/Filament/**/*.php",
      "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
      "./vendor/filament/**/*.blade.php",
    ],
    theme: {
      extend: {
        colors: {
          // يمكنك إضافة ألوان هوية "Espace Tayeb" هنا لاحقاً
        }
      },
    },
    plugins: [],
  }