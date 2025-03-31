# Study zone project

This is a Laravel project using [Filament](https://filamentphp.com/) for the admin panel. Follow the steps below to set up and run the project after cloning.

---

## 🚀 Getting Started

### 1. Clone the Repository (SSH)

```bash
git clone git@gitlab.com:hopecenter/hope_studyzone.git 
cd hope_studyzone
```

### 2. Install Dependencies

Make sure you have PHP, Composer, Node.js, and npm installed.

```bash
composer install
npm install
```

### 3. Copy `.env` File

```bash
cp .env.example .env
```

### 4. Set Application Key

```bash
php artisan key:generate
```

### 5. Configure `.env`

Update the `.env` file with your database credentials and other necessary configurations:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

If you're using Filament-specific packages (like Spatie Media Library, Filament Plugins, etc.), make sure to configure their settings as needed.

### 6. Run Migrations and Seeders

```bash
php artisan migrate --seed
```

### 7. Build Frontend Assets

```bash
npm run build
```
> Or use `npm run dev` for development.

### 8. Serve the Application

```bash
php artisan serve
```

The app will typically be available at [http://localhost:8000](http://localhost:8000)

---

## 🛠 Admin Panel (Filament)

To access the Filament admin panel, go to:

```
http://localhost:8000/admin
```


```aiignore
Email:  admin@sz.com
Password: password
```

---

## 📦 Technologies Used

- Laravel
- Filament
- Tailwind CSS
- Alpine.js
- Laravel Sanctum / Breeze / Jetstream (if applicable)
- Spatie Media Library (if applicable)
- Laravel Excel (if applicable)

---

## 📄 License

This project is open-sourced and available under the [MIT license](LICENSE).
