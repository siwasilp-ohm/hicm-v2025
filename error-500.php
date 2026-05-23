<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อผิดพลาดของเซิร์ฟเวอร์ - HICM V2025</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Prompt', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
        }

        .error-code {
            font-size: 5rem;
            font-weight: 800;
            color: #dc2626;
            margin-bottom: 1rem;
            line-height: 1;
        }

        .error-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
        }

        .error-message {
            font-size: 1rem;
            color: #6b7280;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .error-detail {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: left;
            font-size: 0.875rem;
            color: #991b1b;
        }

        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 2rem;
            color: #dc2626;
            opacity: 0.2;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #1f2937;
            border: 1px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        @media (max-width: 640px) {
            .error-container {
                padding: 2rem;
            }

            .error-code {
                font-size: 3rem;
            }

            .error-title {
                font-size: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>

        <div class="error-code">500</div>
        <h1 class="error-title">ข้อผิดพลาดของเซิร์ฟเวอร์</h1>
        <p class="error-message">
            เกิดข้อผิดพลาดไม่คาดคิดบนเซิร์ฟเวอร์<br>
            ทีมของเราได้รับการแจ้งเรียบร้อยแล้ว
        </p>

        <div class="error-detail">
            <strong>หมายเลขข้อผิดพลาด:</strong> 500 Internal Server Error<br>
            <strong>เวลา:</strong> <span id="timestamp"></span><br>
            <strong>ระบบ:</strong> HICM V2025
        </div>

        <div class="action-buttons">
            <a href="/hicm-v2025/pages/dashboard.php" class="btn btn-primary">
                ← กลับไปแดชบอร์ด
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                ← ย้อนกลับ
            </a>
        </div>
    </div>

    <script>
        // Display current timestamp
        const now = new Date();
        const formatter = new Intl.DateTimeFormat('th-TH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        document.getElementById('timestamp').textContent = formatter.format(now);
    </script>
</body>
</html>
