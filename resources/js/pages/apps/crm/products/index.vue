<script setup>
definePage({ meta: { action: 'read', subject: 'Products' } })

const headers = [
  { title: 'نام', key: 'name' },
  { title: 'SKU', key: 'sku' },
  { title: 'دسته', key: 'category' },
  { title: 'قیمت', key: 'price' },
  { title: 'موجودی', key: 'stock_status' },
  { title: 'منبع', key: 'source' },
  { title: 'وضعیت', key: 'status' },
  { title: 'عملیات', key: 'actions', sortable: false },
]

const products = ref([])
const categories = ref([])
const loading = ref(true)
const dialog = ref(false)
const editingId = ref(null)
const search = ref('')

const form = ref({
  name: '',
  sku: '',
  product_category_id: null,
  short_description: '',
  description: '',
  price: 0,
  sale_price: null,
  currency: 'IRR',
  stock_quantity: null,
  stock_status: 'instock',
  image_url: '',
  status: 'active',
})

const stockStatusItems = [
  { title: 'موجود', value: 'instock' },
  { title: 'ناموجود', value: 'outofstock' },
  { title: 'پیش‌سفارش', value: 'onbackorder' },
]

const statusItems = [
  { title: 'فعال', value: 'active' },
  { title: 'پیش‌نویس', value: 'draft' },
  { title: 'آرشیو', value: 'archived' },
]

const sourceLabel = source => ({
  manual: 'دستی',
  woocommerce: 'ووکامرس',
}[source] ?? source)

const resetForm = () => {
  editingId.value = null
  form.value = {
    name: '',
    sku: '',
    product_category_id: null,
    short_description: '',
    description: '',
    price: 0,
    sale_price: null,
    currency: 'IRR',
    stock_quantity: null,
    stock_status: 'instock',
    image_url: '',
    status: 'active',
  }
}

const fetchProducts = async () => {
  loading.value = true
  try {
    const query = search.value ? `?q=${encodeURIComponent(search.value)}` : ''
    const res = await $api(`/products${query}`)
    products.value = res.data ?? res
  } finally {
    loading.value = false
  }
}

const fetchCategories = async () => {
  try {
    const res = await $api('/product-categories')
    categories.value = res.categories ?? []
  } catch {
    categories.value = []
  }
}

const openCreate = () => {
  resetForm()
  dialog.value = true
}

const openEdit = item => {
  if (item.source === 'woocommerce')
    return

  editingId.value = item.id
  form.value = {
    name: item.name,
    sku: item.sku ?? '',
    product_category_id: item.product_category_id ?? item.category?.id ?? null,
    short_description: item.short_description ?? '',
    description: item.description ?? '',
    price: Number(item.price ?? 0),
    sale_price: item.sale_price != null ? Number(item.sale_price) : null,
    currency: item.currency ?? 'IRR',
    stock_quantity: item.stock_quantity,
    stock_status: item.stock_status ?? 'instock',
    image_url: item.image_url ?? '',
    status: item.status ?? 'active',
  }
  dialog.value = true
}

const saveProduct = async () => {
  if (editingId.value) {
    await $api(`/products/${editingId.value}`, { method: 'PUT', body: form.value })
  } else {
    await $api('/products', { method: 'POST', body: form.value })
  }

  dialog.value = false
  resetForm()
  await fetchProducts()
}

const deleteProduct = async item => {
  if (item.source === 'woocommerce')
    return

  await $api(`/products/${item.id}`, { method: 'DELETE' })
  await fetchProducts()
}

let searchTimer

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchProducts, 300)
})

onMounted(async () => {
  await Promise.all([fetchProducts(), fetchCategories()])
})
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4">
      <div>
        <h5 class="text-h5 mb-1">
          کاتالوگ محصول
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-0">
          مدیریت محصولات دستی و مشاهده محصولات همگام‌شده از ووکامرس
        </p>
      </div>
      <div class="d-flex flex-wrap gap-3">
        <AppTextField
          v-model="search"
          label="جستجو"
          density="compact"
          prepend-inner-icon="tabler-search"
          hide-details
          style="min-width: 220px;"
        />
        <VBtn
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          محصول جدید
        </VBtn>
      </div>
    </VCardText>

    <VDataTable
      :headers="headers"
      :items="products"
      :loading="loading"
    >
      <template #item.category="{ item }">
        {{ item.category?.name ?? '—' }}
      </template>
      <template #item.price="{ item }">
        {{ Number(item.price ?? 0).toLocaleString('fa-IR') }} {{ item.currency }}
      </template>
      <template #item.stock_status="{ item }">
        <VChip
          size="small"
          :color="item.stock_status === 'instock' ? 'success' : 'warning'"
          variant="tonal"
        >
          {{ stockStatusItems.find(s => s.value === item.stock_status)?.title ?? item.stock_status }}
        </VChip>
      </template>
      <template #item.source="{ item }">
        <VChip
          size="small"
          :color="item.source === 'woocommerce' ? 'info' : 'secondary'"
          variant="tonal"
        >
          {{ sourceLabel(item.source) }}
        </VChip>
      </template>
      <template #item.status="{ item }">
        {{ statusItems.find(s => s.value === item.status)?.title ?? item.status }}
      </template>
      <template #item.actions="{ item }">
        <IconBtn
          v-if="item.source !== 'woocommerce'"
          @click="openEdit(item)"
        >
          <VIcon icon="tabler-edit" />
        </IconBtn>
        <IconBtn
          v-if="item.source !== 'woocommerce'"
          @click="deleteProduct(item)"
        >
          <VIcon icon="tabler-trash" />
        </IconBtn>
      </template>
    </VDataTable>
  </VCard>

  <VDialog
    v-model="dialog"
    max-width="720"
  >
    <VCard :title="editingId ? 'ویرایش محصول' : 'محصول جدید'">
      <VCardText>
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="form.name"
              label="نام محصول *"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.sku"
              label="SKU"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.product_category_id"
              :items="categories.map(c => ({ title: c.name, value: c.id }))"
              label="دسته‌بندی"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model.number="form.price"
              label="قیمت"
              type="number"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model.number="form.sale_price"
              label="قیمت فروش ویژه"
              type="number"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model="form.currency"
              label="ارز"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model.number="form.stock_quantity"
              label="تعداد موجودی"
              type="number"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.stock_status"
              :items="stockStatusItems"
              label="وضعیت موجودی"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="form.image_url"
              label="آدرس تصویر"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.short_description"
              label="توضیح کوتاه"
              rows="2"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.description"
              label="توضیحات"
              rows="3"
            />
          </VCol>
          <VCol cols="12">
            <AppSelect
              v-model="form.status"
              :items="statusItems"
              label="وضعیت"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="dialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          @click="saveProduct"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
