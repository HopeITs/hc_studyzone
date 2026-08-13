<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول الطالب</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        /* Custom styles to mimic Filament inputs precisely */
        .fi-input {
            @apply block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-70 ;
        }
        .fi-select {
            @apply block w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-70;
        }
        .fi-btn {
            @apply inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70;
        }
        .fi-btn-primary {
            @apply fi-btn bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500;
        }
        .fi-btn-gray {
            @apply fi-btn bg-white text-gray-700 border border-gray-300 shadow-sm hover:bg-gray-50 focus:ring-primary-500;
        }
        .fi-btn-danger {
            @apply fi-btn text-red-600 hover:bg-red-50 focus:ring-red-500;
        }
        .fi-label {
            @apply block text-sm font-medium text-gray-700;
        }
        .fi-fieldset {
            @apply rounded-xl border border-gray-200 p-4;
        }
        .fi-legend {
            @apply px-2 text-sm font-medium text-gray-700;
        }
    </style>
</head>

<body class="bg-gray-100 " x-data="entryForm({{ Auth::user()->center_id ?? 'null' }})">
<div class="flex items-center justify-center min-h-screen p-6">
    <div class="w-full bg-white border border-gray-200 shadow-lg max-w-7xl rounded-xl">

        <!-- Header -->
        <div class="p-6 border-b border-gray-200 ">
            <h2 class="text-xl font-bold text-gray-900 ">
                تسجيل دخول: {{ $student->firstname }} {{ $student->lastname }}
            </h2>
        </div>

        <div class="p-6 space-y-6">
            <!-- Alerts -->
            @if (session('success'))
                <div class="p-3 text-green-700 bg-green-100 border border-green-400 rounded-lg">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="p-3 text-red-700 bg-red-100 border border-red-400 rounded-lg">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="p-3 text-red-700 bg-red-100 border border-red-400 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (!$student->completed_data)
                <div class="p-4 text-yellow-700 bg-yellow-100 border border-yellow-400 rounded-lg">
                    <p class="font-bold">⚠️ بيانات الطالب غير مكتملة</p>
                    <p class="mt-2">لا يمكن تسجيل دخولية حتى يتم إكمال البيانات التالية:</p>
                    <ul class="mt-2 list-disc list-inside">
                        @foreach ($student->getEntryValidationErrors() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-4">
                        <a href="{{ route('filament.admin.resources.students.edit', $student) }}" class="text-white fi-btn bg-amber-600 hover:bg-amber-700 focus:ring-amber-500">
                            ✏️ تعديل بيانات الطالب
                        </a>
                    </div>
                </div>
            @endif

            @if ($student->completed_data)
            <form method="POST" action="{{ route('student.entry.store', $student->id) }}" id="entryForm">
                @csrf
                <input type="hidden" name="user_id" value="{{ Auth::id() }}">

                <!-- ═══════════════════════════════════════════════════ -->
                <!-- Entry Details Fieldset -->
                <!-- ═══════════════════════════════════════════════════ -->
                <fieldset class="fi-fieldset">
                    <legend class="fi-legend">تفاصيل الدخول</legend>
                    <div class="grid grid-cols-1 gap-4 mt-2 md:grid-cols-3">

                        @if(auth()->user()->role !== \App\Enums\Roles::CenterAdmin())
                        <div>
                            <label for="center_id" class="mb-1 fi-label">@lang('general.labels.center')</label>
                            <select id="center_id" name="center_id" required
                                    x-model="centerId"
                                    x-init="if(centerId) fetchCategories()"
                                    @change="fetchCategories(); services = []"
                                    class="fi-select">
                                <option value="" disabled>اختر مركز</option>
                                @foreach ($centers as $center)
                                    <option value="{{ $center->id }}" {{ $center->id == Auth::user()->center_id ? 'selected' : '' }}>
                                        {{ $center->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @else
                        <input type="hidden" name="center_id" value="{{ auth()->user()->center_id }}" x-model="centerId">
                        @endif

                        <div>
                            <label for="entry_type" class="mb-1 fi-label">نوع الدخول</label>
                            <select id="entry_type" name="entry_type" required class="fi-select">
                                @foreach (__('general.entry-types') as $value => $label)
                                    <option value="{{ $value }}" {{ $value == 1 ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="entry_time" class="mb-1 fi-label">وقت الدخول</label>
                            <input type="datetime-local" name="entry_time" id="entry_time"
                                   value="{{ now()->format('Y-m-d\TH:i') }}"
                                   max="{{ now()->endOfDay()->format('Y-m-d\TH:i') }}"
                                   class="fi-input" required>
                            @error('entry_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </fieldset>

                <!-- ═══════════════════════════════════════════════════ -->
                <!-- Services Fieldset -->
                <!-- ═══════════════════════════════════════════════════ -->
                <fieldset class="mt-6 fi-fieldset">
                    <legend class="fi-legend">الخدمات المطلوبة</legend>

                    <div class="mt-4 space-y-4">
                        <!-- Info text when empty -->
                        <p x-show="services.length === 0" class="py-4 text-sm text-center text-gray-500 rounded-lg bg-gray-50 ">
                            اختر التصنيف ثم الخدمة التابعة لهذا المركز
                        </p>

                        <!-- Services Repeater Rows -->
                        <template x-for="(service, index) in services" :key="index">
                            <div class="relative p-4 border border-gray-200 rounded-lg bg-gray-50 group">

                                <!-- Delete Button -->
                                <button type="button" @click="removeService(index)"
                                        class="absolute px-2 py-1 text-xs transition-opacity opacity-0 top-2 left-2 group-hover:opacity-100 fi-btn-danger">
                                    حذف
                                </button>

                                <div class="grid items-end grid-cols-1 gap-3 md:grid-cols-7">

                                    <!-- Category Select -->
                                    <div class="md:col-span-2">
                                        <label class="mb-1 text-xs fi-label">التصنيف</label>
                                        <select x-model="service.category_id"
                                                @change="fetchServices(index)"
                                                class="text-sm fi-select">
                                            <option value="" disabled>اختر التصنيف أولاً</option>
                                            <template x-for="cat in categories" :key="cat.id">
                                                <option :value="cat.id" x-text="cat.name"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <!-- Service Select -->
                                    <div class="md:col-span-4">
                                        <label class="mb-1 text-xs fi-label">الخدمة</label>
                                        <select x-model="service.service_id"
                                                @change="updatePrice(index)"
                                                class="text-sm fi-select"
                                                :disabled="!service.category_id">
                                            <option value="" disabled x-text="service.category_id ? 'اختر الخدمة' : 'اختر التصنيف أولاً'"></option>
                                            <template x-for="serv in service.available_services" :key="serv.id">
                                                <option :value="serv.id" x-text="serv.name"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <!-- Quantity Input -->
                                    <div class="md:col-span-1">
                                        <label class="mb-1 text-xs fi-label">الكمية</label>
                                        <input type="number" x-model="service.quantity"
                                               @input="updateSubtotal(index)"
                                               min="1" max="999"
                                               class="text-center fi-input">
                                    </div>
                                </div>

                                <!-- Hidden inputs to submit data -->
                                <input type="hidden" :name="`services[${index}][category_id]`" :value="service.category_id">
                                <input type="hidden" :name="`services[${index}][service_id]`" :value="service.service_id">
                                <input type="hidden" :name="`services[${index}][price]`" :value="service.price">
                                <input type="hidden" :name="`services[${index}][quantity]`" :value="service.quantity">
                                <input type="hidden" :name="`services[${index}][subtotal]`" :value="service.subtotal">

                                <!-- Subtotal Display per row -->
                                <div x-show="service.price > 0" x-transition class="flex justify-end pt-3 mt-3 border-t border-gray-200 ">
                                    <span class="text-sm font-semibold text-gray-700 ">
                                        المجموع: <span class="text-primary-600 " x-text="formatNumber(service.subtotal) + ' ل.س'"></span>
                                    </span>
                                </div>
                            </div>
                        </template>

                        <!-- Add Service Button -->
                        <button type="button" @click="addService()"
                                class="w-full py-3 border-dashed fi-btn-gray text-primary-600 hover:bg-primary-50 ">
                            + إضافة خدمة جديدة
                        </button>

                        <!-- Grand Total Display -->
                        <div x-show="totalAmount > 0" x-transition
                             class="p-5 mt-2 border-2 rounded-xl"
                             style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.1) 100%); border-color: rgba(59, 130, 246, 0.3);">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 bg-blue-100 rounded-lg ">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 ">عدد الخدمات المختارة</div>
                                        <div class="text-lg font-semibold text-gray-800 ">
                                            <span x-text="services.length"></span> خدمة
                                        </div>
                                    </div>
                                </div>

                                <div class="text-left">
                                    <div class="text-xs text-gray-500 ">الإجمالي الكلي</div>
                                    <div class="text-3xl font-bold text-blue-600 ">
                                        <span x-text="formatNumber(totalAmount)"></span>
                                        <span class="mr-1 text-base font-medium text-gray-500 ">ل.س</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- ═══════════════════════════════════════════════════ -->
                <!-- Actions & Scanner -->
                <!-- ═══════════════════════════════════════════════════ -->
                <div class="flex flex-col justify-between gap-4 mt-6 md:flex-row">
                    <div class="flex gap-3">
                        <a href="{{ route('filament.admin.resources.students.index') }}" class="fi-btn-gray">
                            رجوع
                        </a>
                        <button type="button" @click="startScanner()" class="fi-btn-gray">
                            فتح الكاميرا
                        </button>
                    </div>

                    <button type="submit" class="px-6 fi-btn-primary">
                        حفظ الدخولية
                    </button>
                </div>

                <!-- Scanner Container -->
                <div id="scanner" class="hidden w-full h-64 mt-6 overflow-hidden bg-gray-100 rounded-lg "></div>
            </form>
            @endif
        </div>
    </div>
</div>

<script>
    document.getElementById('entry_time')?.addEventListener('change', function () {
        if (!this.value) return;

        const selectedDate = this.value.slice(0, 10);
        const today = '{{ now()->toDateString() }}';

        if (selectedDate !== today) {
            alert(@json(__('general.messages.non_today_entry_date_body')));
        }
    });

    document.addEventListener('alpine:init', () => {
        Alpine.data('entryForm', (defaultCenterId) => ({
            centerId: defaultCenterId,
            categories: [],
            services: [],
            html5QrCode: null,

            // Fetch categories based on center
            async fetchCategories() {
                if (!this.centerId) {
                    this.categories = [];
                    return;
                }
                try {
                    const response = await fetch(`/api/centers/${this.centerId}/categories`);
                    this.categories = await response.json();
                } catch (error) {
                    console.error('Error fetching categories:', error);
                }
            },

            // Add new empty service row
            addService() {
                this.services.push({
                    category_id: '',
                    service_id: '',
                    available_services: [],
                    price: 0,
                    quantity: 1,
                    subtotal: 0
                });
            },

            // Remove service row
            removeService(index) {
                this.services.splice(index, 1);
            },

            // Fetch services for specific category
            async fetchServices(index) {
                this.services[index].service_id = '';
                this.services[index].available_services = [];
                this.services[index].price = 0;
                this.services[index].subtotal = 0;

                const categoryId = this.services[index].category_id;
                if (!categoryId || !this.centerId) return;

                try {
                    const response = await fetch(`/api/centers/${this.centerId}/categories/${categoryId}/services`);
                    this.services[index].available_services = await response.json();
                } catch (error) {
                    console.error('Error fetching services:', error);
                }
            },

            // Update price when service is selected
            updatePrice(index) {
                const serviceId = this.services[index].service_id;
                const selectedService = this.services[index].available_services.find(s => s.id == serviceId);

                this.services[index].price = selectedService ? selectedService.price : 0;
                this.services[index].quantity = 1; // Reset quantity
                this.updateSubtotal(index);
            },

            // Update subtotal
            updateSubtotal(index) {
                const price = parseFloat(this.services[index].price) || 0;
                const qty = parseInt(this.services[index].quantity) || 1;
                this.services[index].subtotal = price * Math.max(1, qty);
            },

            // Computed Total Amount
            get totalAmount() {
                return this.services.reduce((total, service) => {
                    return total + (parseFloat(service.subtotal) || 0);
                }, 0);
            },

            // Format number
            formatNumber(num) {
                return new Intl.NumberFormat('ar-SY').format(num);
            },

            // QR Scanner
            startScanner() {
                const scannerEl = document.getElementById('scanner');
                scannerEl.classList.remove('hidden');

                if (!this.html5QrCode) {
                    this.html5QrCode = new Html5Qrcode('scanner');
                }

                Html5Qrcode.getCameras().then(devices => {
                    if (devices.length) {
                        const cameraId = devices[0].id;
                        this.html5QrCode.start(cameraId, {
                            fps: 10,
                            qrbox: 250
                        }, qrCodeMessage => {
                            this.html5QrCode.stop();
                            scannerEl.classList.add('hidden');
                            window.location.href = qrCodeMessage;
                        }, errorMessage => {
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
