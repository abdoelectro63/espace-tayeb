/** @type {import('tailwindcss').Config} */
export default {
    content: [
      "./resources/**/*.blade.php",
      "./resources/**/*.js",
      "./resources/**/*.vue",
      "./app/Filament/**/*.php",
      "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
      "./vendor/filament/**/*.blade.php",
      "./resources/views/filament/resources/orders/widgets/*.blade.php",
      
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