<x-filament::card>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold">Percentage of Entries per Hour (Only Active Days)</h2>
        <form method="POST" action="{{ route('filament.widgets.export-hourly-average') }}">
            @csrf
            <x-filament::button type="submit" color="warning">
                Export to Excel
            </x-filament::button>
        </form>
    </div>

    <div class="w-full h-[400px]">
        <canvas
            x-data="{
                chart: null,
                init() {
                    this.chart = new Chart($refs.canvas, {
                        type: 'bar',
                        data: @js($this->data()),
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true }
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
