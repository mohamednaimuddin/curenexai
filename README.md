# Homeopathic Doctor's Assistant

A comprehensive web application designed specifically for certified Homeopathic Doctors to streamline clinical practice with AI-powered remedy suggestions, patient management, and reference tools.

---

## 🚀 Features

### ✅ **Core Features (MVP)**

#### 1. **Patient Management**
- Complete patient records with demographics, medical history, allergies
- Track chronic conditions and family history
- Upload and manage lab reports (PDF, Images)
- Follow-up scheduling and tracking
- Patient search and filtering

#### 2. **Consultation Management**
- Record detailed case history
- Document symptoms with location, sensation, modality
- Track mental state, thermal characteristics, appetite, sleep patterns
- Categorize symptoms by body systems
- Link consultations to prescriptions

#### 3. **Repertory Search**
- Fast keyword-based rubric search
- Browse by categories (Mind, Head, Stomach, Skin, etc.)
- View remedy grades (1, 2, 3)
- Multiple repertory sources (Kent, Boenninghausen)
- Full-text search capabilities

#### 4. **Materia Medica Reference**
- Comprehensive remedy database (1000+ remedies)
- Detailed keynote symptoms and clinical indications
- System-wise symptom breakdown
- Modalities (aggravation/amelioration)
- Relationship with other remedies
- Dosage and potency notes

#### 5. **AI-Powered Suggestions** 🤖
- Integrated with Google Gemini API
- Intelligent remedy matching based on symptoms
- Case analysis and summary
- Differential diagnosis between similar remedies
- Suggested potencies with medical disclaimers

#### 6. **Prescription Module**
- Create and save prescriptions
- Multi-remedy prescriptions with dosages
- Frequency, duration, and timing instructions
- Diet restrictions and lifestyle advice
- Print-friendly format
- Track prescription history

#### 7. **Doctor Authentication**
- Secure login/registration system
- Profile management
- Session management with timeout
- Activity logging for security

---

## 📋 System Requirements

### **Server Requirements**
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Apache/Nginx**: with mod_rewrite enabled
- **PDO Extension**: enabled

### **Recommended Setup**
- **XAMPP**: 8.0+ (includes Apache, MySQL, PHP)
- **Memory**: 512MB minimum
- **Disk Space**: 100MB for application + database

---

## 🛠️ Installation Guide

### **Step 1: Set Up XAMPP**

1. **Download and install XAMPP** from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. **Start Apache and MySQL** from XAMPP Control Panel
3. Navigate to `C:\xampp1\htdocs\` (or your XAMPP htdocs folder)

### **Step 2: Create Database**

1. Open **phpMyAdmin**: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Click **"New"** to create a database
3. Database name: `homeopathic_system`
4. Collation: `utf8mb4_unicode_ci`
5. Click **"Create"**

### **Step 3: Import Database Schema**

1. Select the `homeopathic_system` database
2. Click **"Import"** tab
3. Choose file: `database/schema.sql`
4. Click **"Go"** to execute

This will create all necessary tables with the following structure:
- ✅ 12 tables (doctors, patients, consultations, symptoms, remedies, repertory, prescriptions, etc.)
- ✅ Default admin account
- ✅ Sample data and indexes
- ✅ Triggers and views

### **Step 4: Configure Application**

1. Open `config/config.php`
2. Update database credentials if needed:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Set your MySQL password
define('DB_NAME', 'homeopathic_system');
```

3. Set your Google Gemini API key for AI features:

```php
define('GEMINI_API_KEY', 'your-gemini-api-key-here');
```

**Get Gemini API Key**: [https://makersuite.google.com/app/apikey](https://makersuite.google.com/app/apikey)

### **Step 5: Set Permissions**

Make sure these folders are writable (chmod 755):
- `uploads/`
- `uploads/lab_reports/`
- `uploads/profile_images/`
- `logs/`

### **Step 6: Access Application**

1. Open browser and go to: [http://localhost/Homeo](http://localhost/Homeo)
2. You'll be redirected to login page

### **Default Admin Login**
- **Email**: `admin@homeo.local`
- **Password**: `admin123`

⚠️ **Important**: Change the default admin password after first login!

---

## 📁 Project Structure

```
Homeo/
├── config/
│   └── config.php              # Application configuration
├── includes/
│   ├── init.php                # Initialization & security
│   ├── database.php            # Database connection & query builder
│   ├── functions.php           # Helper functions
│   ├── header.php              # Common header template
│   └── footer.php              # Common footer template
├── assets/
│   ├── css/
│   │   └── style.css           # Main stylesheet
│   └── js/
│       └── main.js             # Main JavaScript
├── database/
│   └── schema.sql              # Database structure
├── uploads/
│   ├── lab_reports/            # Patient lab reports
│   └── profile_images/         # Doctor profile images
├── patients/                   # Patient management modules
├── consultations/              # Consultation modules
├── repertory/                  # Repertory search
├── materia-medica/             # Remedy reference
├── prescriptions/              # Prescription module
├── ai/                         # AI suggestions
├── login.php                   # Login page
├── register.php                # Registration page
├── dashboard.php               # Main dashboard
├── index.php                   # Entry point
└── README.md                   # This file
```

---

## 🔐 Security Features

- ✅ **CSRF Protection**: All forms protected with CSRF tokens
- ✅ **Password Hashing**: Bcrypt with cost factor 12
- ✅ **SQL Injection**: Prepared statements via PDO
- ✅ **XSS Protection**: Input sanitization and output escaping
- ✅ **Session Security**: Session timeout, regeneration
- ✅ **Activity Logging**: Track all user actions
- ✅ **Secure Headers**: X-Frame-Options, X-XSS-Protection

---

## 🎨 Design Features

- **Modern UI**: Clean, professional gradient design
- **Responsive**: Works on desktop, tablet, mobile
- **Intuitive Navigation**: Sidebar with categorized menu
- **Color-Coded**: Visual feedback for status/categories
- **Icons**: Font Awesome 6.4 integration
- **Smooth Animations**: CSS transitions throughout

---

## 🔧 Configuration Options

### **API Configuration** (config/config.php)

```php
define('AI_ENABLED', true);                    // Enable/disable AI features
define('GEMINI_API_KEY', 'your-key');         // Google Gemini API key
define('MAX_FILE_SIZE', 5 * 1024 * 1024);     // 5MB file upload limit
define('SESSION_LIFETIME', 3600);              // 1 hour session timeout
```

### **Homeopathy Settings**

```php
define('DEFAULT_POTENCY', '30C');
define('AVAILABLE_POTENCIES', [
    '3X', '6X', '12X', '30X',
    '3C', '6C', '12C', '30C', '200C', '1M', '10M', '50M', 'CM',
    'Q', 'LM1', 'LM2', 'LM3'
]);
```

---

## 📊 Database Schema Highlights

### **Core Tables**
- `doctors` - Doctor accounts and profiles
- `patients` - Patient demographics and history
- `consultations` - Case records with symptoms
- `symptoms` - Detailed symptom tracking
- `remedies` - Materia Medica database
- `repertory` - Rubrics with remedy mappings
- `prescriptions` - Treatment prescriptions
- `ai_suggestions_log` - AI interaction history

### **Relationships**
- One doctor → Many patients
- One patient → Many consultations
- One consultation → Many symptoms
- One consultation → One prescription
- One prescription → Many remedies

---

## 🤖 AI Integration (Gemini API)

### **Features**
- Symptom-to-remedy matching
- Case analysis and summary
- Differential diagnosis
- Potency suggestions

### **How It Works**
1. Doctor enters patient symptoms
2. System compiles symptom data
3. Sends structured query to Gemini API
4. AI analyzes and suggests remedies
5. Results saved in `ai_suggestions_log`
6. Doctor reviews and finalizes prescription

### **API Request Format**
```json
{
  "symptoms": ["headache left side", "aggravation by light", "nausea"],
  "modalities": {"aggravation": ["light", "noise"], "amelioration": ["rest"]},
  "thermal_state": "chilly",
  "mental_state": "anxious"
}
```

---

## 🚧 Future Enhancements (Post-MVP)

- [ ] Appointment scheduling calendar
- [ ] Billing and invoicing module
- [ ] Patient progress charts/graphs
- [ ] Voice-to-text symptom recording
- [ ] Offline mode with sync
- [ ] Multi-doctor clinic management
- [ ] Inventory management for medicines
- [ ] SMS/Email notifications
- [ ] Mobile app (iOS/Android)
- [ ] Telemedicine integration

---

## 📝 Usage Guidelines

### **For Doctors**

1. **Register** with your credentials (BHMS/MD number)
2. **Add patients** with complete history
3. **Create consultations** for each visit
4. **Search repertory** for symptom rubrics
5. **Reference Materia Medica** for remedy details
6. **Use AI suggestions** for case analysis
7. **Write prescriptions** with appropriate potencies
8. **Track follow-ups** for patient continuity

### **Best Practices**

- ✅ Always document detailed case notes
- ✅ Use AI as a **suggestion tool**, not replacement
- ✅ Cross-reference with materia medica
- ✅ Schedule follow-ups for chronic cases
- ✅ Update patient history regularly
- ✅ Backup database periodically

---

## 🐛 Troubleshooting

### **Common Issues**

**1. Cannot connect to database**
- Check MySQL is running in XAMPP
- Verify database credentials in `config/config.php`
- Ensure database `homeopathic_system` exists

**2. 404 errors on pages**
- Enable mod_rewrite in Apache
- Check .htaccess file exists (if using)

**3. File upload fails**
- Check `uploads/` folder has write permissions
- Verify `MAX_FILE_SIZE` in config
- Check PHP upload_max_filesize setting

**4. AI suggestions not working**
- Verify Gemini API key is set correctly
- Check internet connection
- Ensure `AI_ENABLED` is true

**5. Session expires quickly**
- Increase `SESSION_LIFETIME` in config
- Check server session settings

---

## 📞 Support & Documentation

### **For Assistance**
- Review this README thoroughly
- Check logs in `logs/` folder
- Examine browser console for JavaScript errors
- Check PHP error logs

### **Medical Disclaimer**
⚠️ This system is a **clinical decision support tool** for certified homeopathic doctors. It does NOT replace professional medical judgment. Doctors must:
- Verify all AI suggestions
- Use clinical experience
- Follow ethical guidelines
- Maintain patient confidentiality

---

## 📜 License

This project is for **educational and professional use only** by licensed homeopathic practitioners.

---

## 🙏 Credits

- **Design**: Modern gradient UI with professional aesthetics
- **Icons**: Font Awesome 6.4
- **AI**: Google Gemini API
- **Framework**: Pure PHP with PDO
- **Database**: MySQL 5.7+

---

## ✅ System Status

- ✅ Database schema created
- ✅ Authentication system ready
- ✅ Dashboard interface complete
- ✅ Core PHP backend functional
- ✅ Modern UI/UX implemented
- ⏳ Patient management (in progress)
- ⏳ Repertory module (next)
- ⏳ Materia Medica (next)
- ⏳ AI integration (next)
- ⏳ Prescription module (next)

---

**Version**: 1.0.0  
**Last Updated**: November 24, 2025  
**Status**: MVP Development Phase

---

**Start improving homeopathic practice today!** 🌿💊
