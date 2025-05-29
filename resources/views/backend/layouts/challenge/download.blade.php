<!DOCTYPE html>
<html lang="en">

<head>
    <title>Download App</title>
    <meta charset="utf-8" />
    <meta name="description" content="" />
    <meta name="keywords" content="" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />

    @include('backend.partials.style')
</head>

<body id="kt_body" class="auth-bg" style="background: linear-gradient(to right, #ff7e5f, #feb47b); height: 100vh; font-family: 'Arial', sans-serif; color: #333;">
    <div class="d-flex flex-column flex-root">
        <div class="d-flex flex-column flex-lg-row flex-column-fluid">
            <div class="d-flex flex-column flex-lg-row-fluid py-10">
                <div class="d-flex flex-center flex-column flex-column-fluid">
                    <div class="w-lg-500px p-10 p-lg-15 mx-auto text-center">
                        <!-- Card for Challenge Details -->
                        <div style="background-color: #fff; border-radius: 20px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); padding: 30px; margin-bottom: 30px;">
                            <h1 style="font-size: 2.5rem; color: #333; font-weight: 700; margin-bottom: 20px;">{{ $challenge->name }}</h1>
                            <p style="font-size: 1.1rem; color: #555; margin-bottom: 25px; line-height: 1.6;">{{ $challenge->description }}</p>
                        </div>

                        <!-- Call to Action -->
                        <p style="font-size: 1.1rem; color: #fff; margin-bottom: 30px;">To get started with the challenge, download the app:</p>

                        <a href="{{ $downloadLink }}" target="_blank">
                            <button style="padding: 14px 30px; background-color: #feb47b; color: #fff; border: none; border-radius: 50px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: background-color 0.3s ease;">
                                Download App
                            </button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('backend.partials.script')
</body>


</html>
