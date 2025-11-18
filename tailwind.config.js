/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./app/**/*.php",
    "./public/js/**/*.js",
    "./tmp/**/*.php"
  ],
  theme: {
    extend: {
      fontFamily: {
        body: ["Inter", "system-ui", "sans-serif"],
        display: ["Playfair Display", "serif"]
      },
      colors: {
        night: {
          900: "#0b1120",
          800: "#111c33",
          700: "#15213f"
        },
        accent: {
          400: "#38bdf8",
          500: "#0ea5e9",
          600: "#0284c7"
        }
      },
      boxShadow: {
        glow: "0 25px 60px -25px rgba(14,165,233,0.55)",
        frame: "0 0 0 1px rgba(148, 163, 184, 0.15) inset"
      }
    }
  },
  plugins: []
};
