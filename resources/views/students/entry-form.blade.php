<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول الطالب</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>

<body class="bg-gray-100" x-data="scannerApp">
<div class="flex items-center justify-center min-h-screen p-24">
    <div class="w-full p-6 bg-white rounded-lg shadow-xl ">
        <h2 class="mb-6 text-xl font-bold text-gray-900">
            تسجيل دخول: {{ $student->firstname }} {{ $student->lastname }}
        </h2>

        @if (session('success'))
            <div class="p-3 mb-6 text-green-700 bg-green-100 border border-green-400 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="p-3 mb-6 text-red-700 bg-red-100 border border-red-400 rounded">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <ul class="p-3 mb-6 text-red-700 bg-red-100 border border-red-400 rounded">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if (!$student->completed_data)
            <div class="p-4 mb-6 text-yellow-700 bg-yellow-100 border border-yellow-400 rounded">
                <p class="font-bold">⚠️ بيانات الطالب غير مكتملة</p>
                <p class="mt-2">لا يمكن تسجيل دخولية حتى يتم إكمال البيانات التالية:</p>
                <ul class="mt-2 list-disc list-inside">
                    @foreach ($student->getEntryValidationErrors() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <div class="mt-4">
                    <a href="{{ route('filament.admin.resources.students.edit', $student) }}"
                       class="inline-flex items-center px-4 py-2 text-sm font-medium text-white rounded-md bg-amber-600 hover:bg-amber-700">
                        ✏️ تعديل بيانات الطالب
                    </a>
                </div>
            </div>
        @endif

        @if ($student->completed_data)
        <form method="POST" action="{{ route('student.entry.store', $student->id) }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ Auth::id() }}">

            <div class="grid grid-cols-3 gap-8">

                <div class="col-span-1">
                    <label for="center_id" class="block mb-1 text-sm font-medium text-gray-700">
                        @lang('general.labels.center')
                    </label>
                    <select id="center_id" name="center_id" required
                            class="block w-full h-10 text-sm border border-gray-400 rounded-md shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50">
                        <option disabled @selected(Auth::user()->center_id === null) >اختر مركز</option>
                        @foreach ($centers as  $center)
                            <option
                                value="{{ $center->id }}" {{ $center->id == Auth::user()->center_id ? 'selected' : '' }}>
                                {{ $center->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-span-1">
                    <label for="entry_type" class="block mb-1 text-sm font-medium text-gray-700">
                        نوع الدخول
                    </label>
                    <select id="entry_type" name="entry_type" required
                            class="block w-full h-10 text-sm border border-gray-300 rounded-md shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50">
                        @foreach (__('general.entry-types') as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-span-1">
                    <label for="entry_time" class="block mb-1 text-sm font-medium text-gray-700">
                        وقت الدخول
                    </label>
                    <input type="datetime-local" name="entry_time" id="entry_time"
                           value="{{ now()->format('Y-m-d\TH:i') }}"
                           class="block w-full h-10 text-sm border border-gray-300 rounded-md shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                           required>
                </div>

                <fieldset class="col-span-3 p-4 border border-gray-300 rounded-md">
                    <legend class="mb-2 text-sm font-medium text-gray-700">الخدمات</legend>
                    <div class="grid grid-cols-5 gap-2">
                        <label class="flex items-center space-x-6">
                            <input type="checkbox" name="use_printer" class="size-4 me-3" value="1">
                            <span>خدمة طباعة</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="use_drawer" class="size-4 me-3" value="1">
                            <span>خدمة مرسم</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="use_mecrator" class="size-4 me-3" value="1">
                            <span>خدمة ميكراتور</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="use_plotter" class="size-4 me-3" value="1">
                            <span>خدمة بلوتر</span>
                        </label>
                         <label class="flex items-center space-x-2">
                            <input type="checkbox" name="use_internet" class="size-4 me-3" value="1">
                            <span>خدمة إنترنت</span>
                        </label>
                    </div>
                </fieldset>

                <div class="flex justify-between col-span-3 gap-4 mt-4">
                    <a href="{{ route('filament.admin.resources.students.index') }}"
                       class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-900 rounded-md hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                        رجوع
                    </a>

                    <button type="button" @click="startScanner()"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-900 rounded-md hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                        فتح الكاميرا
                    </button>

                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white rounded-md bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                        إرسال
                    </button>
                </div>
            </div>
        </form>

        <div id="scanner" class="w-full h-64 mt-6 bg-gray-100 rounded-lg"></div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('scannerApp', () => ({
            html5QrCode: null,
            startScanner() {
                if (!this.html5QrCode) {
                    this.html5QrCode = new Html5Qrcode('scanner');
                }
                Html5Qrcode.getCameras().then(devices => {
                    if (devices.length) {
                        const cameraId = devices[0].id;
                        this.html5QrCode.start(cameraId, {
                                fps: 10,
                                qrbox: 250
                            },
                            qrCodeMessage => {
                                this.html5QrCode.stop();
                                window.location.href = `${qrCodeMessage}`;
                            },
                            errorMessage => {
                                console.warn(errorMessage);
                            });
                    }
                }).catch(err => console.error('Camera init error', err));
            }
        }));
    });
</script>
</body>

</html>
