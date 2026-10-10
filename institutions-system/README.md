# Institutions System

## Overview
The Institutions System is a web application designed to manage institutions, administrators, and data entry personnel. It allows developers to create and manage administrators, who can then add data entry personnel. The application ensures that data entry personnel can only input specific data without accessing other personnel's data.

## Features
- **Administrator Management**: Developers can add, modify, and delete administrators.
- **Data Entry Personnel Management**: Administrators can add data entry personnel without the ability to add other administrators.
- **Data Input**: Data entry personnel can input specific data related to families and benefits.
- **Data Export**: The application includes features for exporting data to Excel.
- **Reporting**: Administrators can generate reports with statistics on the data entered.
- **Family Benefits**: A dedicated section for managing benefits associated with each family.
- **Improved UI**: Enhanced color formatting for better user experience.

## Project Structure
```
institutions-system
├── app
│   ├── Console
│   ├── Exceptions
│   ├── Http
│   │   ├── Controllers
│   │   │   ├── Admin
│   │   │   │   ├── AdministratorController.php
│   │   │   │   ├── DataEntryController.php
│   │   │   │   └── ReportController.php
│   │   │   └── Auth
│   │   ├── Middleware
│   │   └── Requests
│   ├── Models
│   │   ├── Administrator.php
│   │   ├── DataEntry.php
│   │   ├── Institution.php
│   │   ├── Family.php
│   │   ├── Benefit.php
│   │   └── Record.php
│   ├── Policies
│   └── Exports
│       └── RecordsExport.php
├── bootstrap
├── config
├── database
│   ├── migrations
│   │   ├── 2026_01_01_000000_create_institutions_table.php
│   │   ├── 2026_01_01_000001_create_administrators_table.php
│   │   ├── 2026_01_01_000002_create_data_entries_table.php
│   │   ├── 2026_01_01_000003_create_families_table.php
│   │   ├── 2026_01_01_000004_create_benefits_table.php
│   │   └── 2026_01_01_000005_create_records_table.php
│   └── seeders
│       └── DatabaseSeeder.php
├── public
│   └── index.php
├── resources
│   ├── views
│   │   ├── layouts
│   │   │   └── app.blade.php
│   │   ├── admin
│   │   │   ├── dashboard.blade.php
│   │   │   ├── administrators
│   │   │   │   └── index.blade.php
│   │   │   ├── data_entries
│   │   │   │   └── index.blade.php
│   │   │   ├── reports
│   │   │   │   └── index.blade.php
│   │   │   └── families
│   │   │       └── index.blade.php
│   ├── css
│   │   └── app.css
│   └── js
│       └── app.js
├── routes
│   ├── web.php
│   └── api.php
├── storage
│   ├── app
│   └── framework
├── tests
│   ├── Feature
│   └── Unit
├── .env.example
├── artisan
├── composer.json
├── package.json
├── vite.config.js
└── README.md
```

## Installation
1. Clone the repository:
   ```
   git clone <repository-url>
   ```
2. Navigate to the project directory:
   ```
   cd institutions-system
   ```
3. Install dependencies:
   ```
   composer install
   npm install
   ```
4. Set up your environment file:
   ```
   cp .env.example .env
   ```
5. Generate the application key:
   ```
   php artisan key:generate
   ```
6. Run migrations:
   ```
   php artisan migrate
   ```
7. Start the development server:
   ```
   php artisan serve
   ```

## Usage
- Access the application at `http://localhost:8000`.
- Use the provided interfaces to manage administrators and data entry personnel.

## Contributing
Contributions are welcome! Please submit a pull request or open an issue for any enhancements or bug fixes.

## License
This project is licensed under the MIT License. See the LICENSE file for details.