<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Connect IT</title>
    <link rel="stylesheet" href="/php-app/public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0B141A 0%, #151B26 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        .login-card {
            background: rgba(21, 27, 38, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-icon {
            width: 48px;
            height: 48px;
            background: var(--sn-green);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--sn-dark);
            margin-bottom: 1rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-dim);
        }
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
        }
        .input-group input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            color: white;
            transition: all 0.2s;
        }
        .input-group input:focus {
            outline: none;
            border-color: var(--sn-green);
            background: rgba(255, 255, 255, 0.1);
        }
        .btn-login {
            width: 100%;
            background: var(--sn-green);
            color: var(--sn-dark);
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(129, 181, 50, 0.3);
        }
        .alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">C</div>
                <h1 style="font-size: 1.5rem; font-weight: 700;">Connect IT</h1>
                <p style="color: var(--text-dim); font-size: 0.875rem; margin-top: 0.5rem;">Sign in to your account</p>
            </div>

            <?php if ($error = \Core\Application::$app->session->getFlash('error')): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="/php-app/login" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" value="<?php echo $email ?? ''; ?>" required placeholder="name@company.com">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="btn-login">Sign In</button>
            </form>

            <div style="text-align: center; margin-top: 2rem;">
                <p style="font-size: 0.875rem; color: var(--text-dim);">
                    Don't have an account? <a href="#" style="color: var(--sn-green); text-decoration: none;">Contact Admin</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
