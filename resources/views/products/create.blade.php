<x-panel-layout title="Add product" subtitle="New products appear in the shop immediately">
    <x-slot:actions>
        <a href="{{ route('products.index') }}" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> All products</a>
    </x-slot:actions>

    <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('products._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('products.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary"><i class="fa-solid fa-plus"></i> Add product</button>
        </div>
    </form>
</x-panel-layout>
