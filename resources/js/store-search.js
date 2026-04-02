export function registerStoreSearch(AlpineInstance) {
    AlpineInstance.data('storeSearch', (config) => ({
        endpoint: config.url,
        q: '',
        open: false,
        loading: false,
        results: [],
        message: '',

        onInput() {
            if (this.q.trim().length < 2) {
                this.results = [];
                this.message = '';
                this.open = false;

                return;
            }

            this.loading = true;
            this.message = '';

            const url = `${this.endpoint}?q=${encodeURIComponent(this.q.trim())}`;

            window
                .fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                .then((res) => res.json())
                .then((data) => {
                    this.results = data.products ?? [];
                    this.message =
                        this.results.length === 0 ? 'لا توجد نتائج' : '';
                    this.open = true;
                })
                .catch(() => {
                    this.results = [];
                    this.message = 'تعذر البحث';
                    this.open = true;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
    }));
}
