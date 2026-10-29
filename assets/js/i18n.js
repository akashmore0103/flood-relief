/**
 * Internationalization (i18n) Engine for Flood Relief System
 * Supports English, Marathi (मराठी), and Hindi (हिंदी)
 */

const APP_TRANSLATIONS = {
    en: {
        app_title: "Flood Risk & Relief System",
        nav_home: "Home",
        nav_map: "Live Risk Map",
        nav_dams: "Dam Telemetry",
        nav_missing: "Missing & Found",
        nav_sos: "Emergency SOS",
        nav_camps: "Relief Camps",
        nav_citizen_watch: "Citizen Watch",
        nav_simulation: "Simulation Studio",
        nav_dashboard: "Dashboard",
        nav_login: "Login",
        nav_register: "Register",
        nav_logout: "Logout",
        emergency_helpline: "Disaster Helpline",
        critical_alert: "CRITICAL FLOOD ALERT",
        high_risk: "HIGH RISK",
        moderate_risk: "MODERATE RISK",
        low_risk: "LOW / SAFE",
        dam_storage: "Dam Storage",
        outflow_cusecs: "Discharge (Outflow)",
        danger_mark: "Danger Mark",
        warning_level: "Warning Level",
        river_level: "Current River Level",
        water_severity: "Flood Water Severity",
        ankle_level: "Ankle-Level (0 - 1 ft)",
        waist_level: "Waist-Level (3 - 4 ft)",
        roof_level: "Roof / Overhead (> 8 ft)",
        mobile_number: "10-Digit Mobile Number",
        call_citizen: "Call Citizen 📞",
        emergency_speed_dial: "Emergency Speed Dial",
        national_emergency: "National Emergency",
        disaster_helpline_1077: "Disaster Control Room",
        ambulance_108: "Ambulance & Medical",
        report_missing_title: "Report Missing Person",
        report_found_title: "Report Found / Rescued Person",
        search_registry: "Search Missing / Found Registry",
        search_placeholder: "Search by name, district, or river basin...",
        filter_status: "Status Filter",
        simulate_flash_flood: "Simulate Flash Flood",
        reset_simulation: "Reset to Normal Telemetry",
        predictive_forecast_24h: "24-48h Predictive Flood Forecast",
        my_saved_dam: "My Monitored Dam",
        my_saved_river: "My Monitored River",
        all_status: "All Statuses",
        status_missing: "Missing",
        status_found: "Found",
        status_sheltered: "Sheltered in Camp",
        status_reunited: "Reunited with Family",
        person_name: "Full Name",
        age: "Age",
        gender: "Gender",
        last_seen: "Last Seen Location",
        contact_number: "Contact Phone Number",
        photo: "Photograph",
        details: "Detailed Description",
        submit: "Submit Report",
        cancel: "Cancel"
    },
    mr: {
        app_title: "पूर धोका अंदाज व मदत छावणी प्रणाली",
        nav_home: "मुख्यपृष्ठ",
        nav_map: "थेट पूर नकाशा",
        nav_dams: "धरण जलसाठा माहिती",
        nav_missing: "हरवलेल्या व्यक्ती नोंदणी",
        nav_sos: "तातडीची मदत (SOS)",
        nav_camps: "मदत छावण्या",
        nav_citizen_watch: "नागरिक पूर कक्ष",
        nav_simulation: "पूर सिम्युलेशन स्टुडिओ",
        nav_dashboard: "डॅशबोर्ड",
        nav_login: "लॉगिन करा",
        nav_register: "नोंदणी करा",
        nav_logout: "बाहेर पडा",
        emergency_helpline: "आपत्ती हेल्पलाइन",
        critical_alert: "अतिधोकादायक पूर इशारा (रेड अलर्ट)",
        high_risk: "उच्च धोका",
        moderate_risk: "मध्यम धोका",
        low_risk: "कमी / सुरक्षित",
        dam_storage: "धरण साठा",
        outflow_cusecs: "पाण्याचा विसर्ग (क्युसेक)",
        danger_mark: "धोका पातळी",
        warning_level: "इशारा पातळी",
        river_level: "सद्य नदी पाणी पातळी",
        water_severity: "पाण्याची पातळी / तीव्रता",
        ankle_level: "घोट्यापर्यंत (० - १ फूट)",
        waist_level: "कमरेपर्यंत (३ - ४ फूट)",
        roof_level: "छतापर्यंत / अतिगंभीर (> ८ फूट)",
        mobile_number: "१०-अंकी मोबाईल क्रमांक",
        call_citizen: "नागरिकाला कॉल करा 📞",
        emergency_speed_dial: "तातडीचे आपत्कालीन क्रमांक",
        national_emergency: "राष्ट्रीय आपत्कालीन सेवा (११२)",
        disaster_helpline_1077: "जिल्हा आपत्ती नियंत्रण कक्ष (१०७७)",
        ambulance_108: "रुग्णवाहिका सेवा (१०८)",
        report_missing_title: "हरवलेल्या व्यक्तीची नोंद करा",
        report_found_title: "सापडलेल्या / आश्रित व्यक्तीची नोंद करा",
        search_registry: "हरवलेल्या/सापडलेल्या व्यक्ती शोधा",
        search_placeholder: "नाव, जिल्हा किंवा नदी खोऱ्याने शोधा...",
        filter_status: "स्थिती फिल्टर",
        simulate_flash_flood: "पूर परिस्थिती सिम्युलेट करा",
        reset_simulation: "सामान्य स्थितीवर रीसेट करा",
        predictive_forecast_24h: "२४ ते ४८ तास पूर अंदाज",
        my_saved_dam: "माझे निवडलेले धरण",
        my_saved_river: "माझी निवडलेली नदी",
        all_status: "सर्व स्थिती",
        status_missing: "लापत्ता / हरवलेले",
        status_found: "सापडलेले",
        status_sheltered: "छावणीत आश्रित",
        status_reunited: "कुटुंबाशी भेटलेले",
        person_name: "पूर्ण नाव",
        age: "वय",
        gender: "लिंग",
        last_seen: "शेवटचे पाहिलेले ठिकाण",
        contact_number: "संपर्क मोबाईल क्रमांक",
        photo: "छायाचित्र (फोटो)",
        details: "सविस्तर माहिती",
        submit: "नोंद पाठवा",
        cancel: "रद्द करा"
    },
    hi: {
        app_title: "बाढ़ जोखिम भविष्यवाणी और राहत शिविर प्रणाली",
        nav_home: "होम",
        nav_map: "लाइव बाढ़ मानचित्र",
        nav_dams: "बांध जल स्तर व डिस्चार्ज",
        nav_missing: "लापता एवं मिले व्यक्ति",
        nav_sos: "आपातकालीन सहायता (SOS)",
        nav_camps: "राहत शिविर",
        nav_citizen_watch: "नागरिक बाढ़ वॉच",
        nav_simulation: "बाढ़ सिमुलेशन स्टूडियो",
        nav_dashboard: "डैशबोर्ड",
        nav_login: "लॉग इन करें",
        nav_register: "पंजीकरण करें",
        nav_logout: "लॉग आउट",
        emergency_helpline: "आपदा हेल्पलाइन",
        critical_alert: "गंभीर बाढ़ चेतावनी (रेड अलर्ट)",
        high_risk: "उच्च जोखिम",
        moderate_risk: "मध्यम जोखिम",
        low_risk: "कम / सुरक्षित",
        dam_storage: "बांध भंडारण",
        outflow_cusecs: "पानी का निकास (क्यूसेक)",
        danger_mark: "खतरे का निशान",
        warning_level: "चेतावनी स्तर",
        river_level: "वर्तमान नदी जल स्तर",
        water_severity: "जलभराव की गंभीरता",
        ankle_level: "टखने तक (0 - 1 फीट)",
        waist_level: "कमर तक (3 - 4 फीट)",
        roof_level: "छत तक / अत्यधिक जलभराव (> 8 फीट)",
        mobile_number: "10-अंकों का मोबाइल नंबर",
        call_citizen: "नागरिक को कॉल करें 📞",
        emergency_speed_dial: "आपातकालीन स्पीड डायल",
        national_emergency: "राष्ट्रीय आपातकालीन सेवा (112)",
        disaster_helpline_1077: "आपदा नियंत्रण कक्ष (1077)",
        ambulance_108: "एम्बुलेंस व चिकित्सा (108)",
        report_missing_title: "लापता व्यक्ति की सूचना दें",
        report_found_title: "मिले / आश्रय प्राप्त व्यक्ति की सूचना दें",
        search_registry: "लापता एवं मिले व्यक्तियों की खोज",
        search_placeholder: "नाम, जिला या नदी घाटी द्वारा खोजें...",
        filter_status: "स्थिति फ़िल्टर",
        simulate_flash_flood: "आकस्मिक बाढ़ सिमुलेशन",
        reset_simulation: "सामान्य स्थिति पर रीसेट करें",
        predictive_forecast_24h: "24-48 घंटे बाढ़ का पूर्वानुमान",
        my_saved_dam: "मेरा चुना हुआ बांध",
        my_saved_river: "मेरी चुनी हुई नदी",
        all_status: "सभी स्थितियां",
        status_missing: "लापता",
        status_found: "मिले",
        status_sheltered: "शिविर में आश्रित",
        status_reunited: "परिवार से पुनर्मिलन",
        person_name: "पूरा नाम",
        age: "आयु",
        gender: "लिंग",
        last_seen: "अंतिम बार देखे जाने का स्थान",
        contact_number: "संपर्क मोबाइल नंबर",
        photo: "तस्वीर (फोटो)",
        details: "विस्तृत विवरण",
        submit: "सूचना दर्ज करें",
        cancel: "रद्द करें"
    }
};

class I18nManager {
    constructor() {
        this.currentLang = this.detectLanguage();
        this.init();
    }

    detectLanguage() {
        const stored = localStorage.getItem('flood_relief_lang');
        if (stored && ['en', 'mr', 'hi'].includes(stored)) {
            return stored;
        }
        const cookieMatch = document.cookie.match(/flood_relief_lang=([^;]+)/);
        if (cookieMatch && ['en', 'mr', 'hi'].includes(cookieMatch[1])) {
            return cookieMatch[1];
        }
        return 'en';
    }

    setLanguage(lang, reloadServer = false) {
        if (!['en', 'mr', 'hi'].includes(lang)) return;
        this.currentLang = lang;
        localStorage.setItem('flood_relief_lang', lang);
        document.cookie = `flood_relief_lang=${lang};path=/;max-age=2592000`;

        this.applyTranslations();
        this.updateActivePicker();

        if (reloadServer) {
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        }
    }

    translate(key, fallback = null) {
        const dict = APP_TRANSLATIONS[this.currentLang] || APP_TRANSLATIONS['en'];
        return dict[key] || (APP_TRANSLATIONS['en'][key] || fallback || key);
    }

    applyTranslations() {
        document.documentElement.lang = this.currentLang;

        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const translation = this.translate(key);
            if (translation) {
                el.textContent = translation;
            }
        });

        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.getAttribute('data-i18n-placeholder');
            const translation = this.translate(key);
            if (translation) {
                el.placeholder = translation;
            }
        });

        document.querySelectorAll('[data-i18n-title]').forEach(el => {
            const key = el.getAttribute('data-i18n-title');
            const translation = this.translate(key);
            if (translation) {
                el.title = translation;
            }
        });
    }

    updateActivePicker() {
        const labelMap = { en: 'English', mr: 'मराठी', hi: 'हिंदी' };
        const labelEl = document.getElementById('currentLanguageLabel');
        if (labelEl) {
            labelEl.textContent = labelMap[this.currentLang] || 'English';
        }
        document.querySelectorAll('.lang-select-option').forEach(opt => {
            if (opt.getAttribute('data-lang') === this.currentLang) {
                opt.classList.add('active', 'fw-bold');
            } else {
                opt.classList.remove('active', 'fw-bold');
            }
        });
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.applyTranslations();
            this.updateActivePicker();

            document.querySelectorAll('.lang-select-option').forEach(opt => {
                opt.addEventListener('click', (e) => {
                    e.preventDefault();
                    const chosen = opt.getAttribute('data-lang');
                    this.setLanguage(chosen, true);
                });
            });
        });
    }
}

window.i18n = new I18nManager();
window.t = (key, fallback) => window.i18n.translate(key, fallback);
