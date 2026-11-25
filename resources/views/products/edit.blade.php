<x-panel-layout title="Edit product" :subtitle="$product->name">
    <x-slot:actions>
        <a href="{{ route('products.index') }}" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> All products</a>
    </x-slot:actions>

    <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('products._form', ['product' => $product])
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('products.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
        </div>
    </form>
</x-panel-layout>
