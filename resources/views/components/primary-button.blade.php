<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-slate-900 border border-transparent rounded-lg font-semibold text-sm text-white tracking-wide hover:bg-indigo-600 hover:-translate-y-px hover:shadow-lg hover:shadow-indigo-500/25 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 active:translate-y-0 transition']) }}>
    {{ $slot }}
</button>
