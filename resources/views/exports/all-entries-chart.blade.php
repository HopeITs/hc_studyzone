<x-filament::card>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold">Entries per Month</h2>
        <form method="POST" action="{{ route('filament.widgets.export-all-entries') }}">
            @csrf
            <x-filament::button type="submit" color="warning">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path d="M4 4a2 2 0 0 1 2-2h7l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4z" />
                    <path d="M14 2v5h5" />
                    <path d="M8 16l2-2-2-2 2-2" />
                    <path d="M12 14l-2-2 2-2" />
                </svg>
            </x-filament::button>
        </form>
    </div>

    <div class="w-full h-[400px]">
        <canvas
            x-data="{
                chart: null,
                init() {
                    this.chart = new Chart($refs.canvas, {
                        type: 'line',
                        data: @js($this->data()),
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            }"
            x-ref="canvas"
            class="w-full h-full"
        ></canvas>
    </div>
</x-filament::card>
