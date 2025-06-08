# 🧠 CogniTest: Dynamic Exam Paper Generator

CogniTest is a role-based web application built to simplify and automate the **generation of university-level question papers**. It allows **HODs, Faculty Members, and Exam Coordinators** to collaboratively create and manage question papers in PDF format using a secure and structured workflow.

---

## 🚀 Features

- Streamlined the exam creation process, reducing paper generation time by 60 percent and ensuring educational standards.
- Implemented 3-tier role-based access system (HOD, Faculty, Exam Coordinator) with secure authentication.
- Developed Question bank supporting 6 Bloom’s taxonomy levels and utilized MySQL database to efficiently manage 100+
- Utilized php mysql database database to efficiently manage 50+ questions 

### 🔐 Role-Based Access
- **HOD**: Pre-registered login (no signup).
- **Faculty & Exam Coordinator**: Common login/signup system with role selection dropdown.

### 🧑‍💼 HOD Dashboard
- **Faculty Assignment**: Approve/reject faculty join requests.
- **View Faculty**: View, edit, delete, or assign courses to faculty.
- **Add/Edit Courses**: Manage course offerings.
- **About Section**: Information section about the system or department.

### 🧑‍🏫 Faculty Dashboard
- **View Courses**: See assigned courses with metadata.
- **Add Questions**: Add unit-wise questions with Bloom's Taxonomy level and marks.
- **Generate Paper**: Fill question paper header, select questions, and submit for approval.
- **View Papers**: View/download approved papers via unique key.

### 📋 Exam Coordinator Dashboard
- **Paper Summary**: View question count and Bloom’s taxonomy distribution.
- **Approve Paper**: Approve generated paper and create a secure alphanumeric key.
- **Generate PDF**: Use key to generate and download the final question paper.

## 🛠️ Tech Stack
- **PHP**
  
## 📦 Installation

### Composer
- Download composer from https://getcomposer.org/
- Navigate to the path /CogniTest and run the below command for the installing dompdf library:
     composer require dompdf/dompdf

