<script setup>
definePage({ meta: { action: 'read', subject: 'Invoicing' } })

const { formatDateTime } = useJalaliDate()

const headers = [
  { title: 'شماره', key: 'number' },
  { title: 'مخاطب', key: 'contact' },
  { title: 'معامله', key: 'deal' },
  { title: 'مبلغ', key: 'total' },
  { title: 'وضعیت', key: 'status' },
  { title: 'تاریخ', key: 'created_at' },
  { title: 'عملیات', key: 'actions', sortable: false },
]

const quotes = ref([])
const products = ref([])
const contacts = ref([])
const deals = ref([])
const loading = ref(true)
const dialog = ref(false)

const form = ref({
  contact_id: null,
  deal_id: null,
  discount: 0,
  tax: 0,
  currency: 'IRR',
  valid_until: '',
  notes: '',
  line_items: [{ product_id: null, description: '', quantity: 1, unit_price: 0, discount: 0 }],
})

const statusLabel = {
  draft: 'پیش‌نویس',
  sent: 'ارسال‌شده',
  accepted: 'تأیید‌شده',
  rejected: 'رد‌شده',
  cancelled: 'لغو‌شده',
}

const fetchQuotes = async () => {
  loading.value = true
  try {
    const res = await $api('/quotes')
    quotes.value = res.data ?? res
  } finally {
    loading.value = false
  }
}

const fetchMeta = async () => {
  const [productRes, contactRes, dealRes] = await Promise.all([
    $api('/products?active_only=1&per_page=200').catch(() => ({ data: [] })),
    $api('/contacts', { query: { per_page: 200 } }).catch(() => ({ data: [] })),
    $api('/deals').catch(() => ({ data: [] })),
  ])

  products.value = productRes.data ?? productRes ?? []
  contacts.value = contactRes.data ?? contactRes ?? []
  deals.value = dealRes.data ?? dealRes ?? []
}

const addLine = () => {
  form.value.line_items.push({ product_id: null, description: '', quantity: 1, unit_price: 0, discount: 0 })
}

const removeLine = index => {
  form.value.line_items.splice(index, 1)
}

const onProductSelect = (index, productId) => {
  const product = products.value.find(p => p.id === productId)
  if (!product)
    return

  form.value.line_items[index].description = product.name
  form.value.line_items[index].unit_price = Number(product.sale_price ?? product.price ?? 0)
}

const saveQuote = async () => {
  await $api('/quotes', { method: 'POST', body: form.value })
  dialog.value = false
  form.value = {
    contact_id: null,
    deal_id: null,
    discount: 0,
    tax: 0,
    currency: 'IRR',
    valid_until: '',
    notes: '',
    line_items: [{ product_id: null, description: '', quantity: 1, unit_price: 0, discount: 0 }],
  }
  await fetchQuotes()
}

const sendQuote = async item => {
  await $api(`/quotes/${item.id}/send`, { method: 'POST' })
  await fetchQuotes()
}

onMounted(async () => {
  await Promise.all([fetchQuotes(), fetchMeta()])
})
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4">
      <div>
        <h5 class="text-h5 mb-1">
          پیش‌فاکتورها
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-0">
          صدور پیش‌فاکتور با انتخاب محصول از کاتالوگ
        </p>
      </div>
      <VBtn
        prepend-icon="tabler-plus"
        @click="dialog = true"
      >
        پیش‌فاکتور جدید
      </VBtn>
    </VCardText>

    <VDataTable
      :headers="headers"
      :items="quotes"
      :loading="loading"
    >
      <template #item.contact="{ item }">
        {{ item.contact?.name ?? '—' }}
      </template>
      <template #item.deal="{ item }">
        {{ item.deal?.title ?? '—' }}
      </template>
      <template #item.total="{ item }">
        {{ Number(item.total ?? 0).toLocaleString('fa-IR') }} {{ item.currency }}
      </template>
      <template #item.status="{ item }">
        <VChip
          size="small"
          variant="tonal"
        >
          {{ statusLabel[item.status] ?? item.status }}
        </VChip>
      </template>
      <template #item.created_at="{ item }">
        {{ item.created_at ? formatDateTime(item.created_at) : '—' }}
      </template>
      <template #item.actions="{ item }">
        <VBtn
          v-if="item.status === 'draft'"
          size="small"
          variant="tonal"
          @click="sendQuote(item)"
        >
          ارسال
        </VBtn>
      </template>
    </VDataTable>
  </VCard>

  <VDialog
    v-model="dialog"
    max-width="900"
  >
    <VCard title="پیش‌فاکتور جدید">
      <VCardText>
        <VRow class="mb-4">
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.contact_id"
              :items="contacts.map(c => ({ title: c.name, value: c.id }))"
              label="مخاطب"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.deal_id"
              :items="deals.map(d => ({ title: d.title, value: d.id }))"
              label="معامله"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model.number="form.discount"
              label="تخفیف کل"
              type="number"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppTextField
              v-model.number="form.tax"
              label="مالیات"
              type="number"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppJalaliDatePicker
              v-model="form.valid_until"
              label="اعتبار تا"
            />
          </VCol>
        </VRow>

        <div class="d-flex align-center justify-space-between mb-3">
          <h6 class="text-h6">
            اقلام
          </h6>
          <VBtn
            size="small"
            variant="tonal"
            prepend-icon="tabler-plus"
            @click="addLine"
          >
            ردیف
          </VBtn>
        </div>

        <div
          v-for="(line, index) in form.line_items"
          :key="index"
          class="mb-4 pa-3 rounded border"
        >
          <VRow>
            <VCol
              cols="12"
              md="5"
            >
              <AppSelect
                v-model="line.product_id"
                :items="products.map(p => ({ title: p.sku ? `${p.name} (${p.sku})` : p.name, value: p.id }))"
                label="محصول"
                clearable
                @update:model-value="onProductSelect(index, $event)"
              />
            </VCol>
            <VCol
              cols="12"
              md="2"
            >
              <AppTextField
                v-model.number="line.quantity"
                label="تعداد"
                type="number"
              />
            </VCol>
            <VCol
              cols="12"
              md="2"
            >
              <AppTextField
                v-model.number="line.unit_price"
                label="قیمت واحد"
                type="number"
              />
            </VCol>
            <VCol
              cols="12"
              md="2"
            >
              <AppTextField
                v-model.number="line.discount"
                label="تخفیف"
                type="number"
              />
            </VCol>
            <VCol
              cols="12"
              md="1"
              class="d-flex align-center"
            >
              <IconBtn
                v-if="form.line_items.length > 1"
                @click="removeLine(index)"
              >
                <VIcon icon="tabler-trash" />
              </IconBtn>
            </VCol>
          </VRow>
        </div>

        <AppTextarea
          v-model="form.notes"
          label="یادداشت"
          rows="2"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="dialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          @click="saveQuote"
        >
          ذخیره پیش‌فاکتور
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
