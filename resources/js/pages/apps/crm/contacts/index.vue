<script setup>
import CrmProductChips from '@/components/crm/CrmProductChips.vue'

definePage({ meta: { action: 'read', subject: 'Contacts' } })

const router = useRouter()

const headers = [
  { title: 'نام', key: 'name' },
  { title: 'ایمیل', key: 'email' },
  { title: 'تلفن', key: 'phone' },
  { title: 'شرکت', key: 'company' },
  { title: 'محصولات', key: 'products', sortable: false },
  { title: 'تاریخ ثبت', key: 'created_at_jalali' },
  { title: 'عملیات', key: 'actions', sortable: false },
]

const contacts = ref([])
const loading = ref(true)
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const dialog = ref(false)
const form = ref({ name: '', email: '', phone: '', company: '' })

const fetchContacts = async () => {
  loading.value = true
  try {
    const res = await $api('/contacts', {
      query: {
        page: page.value,
        per_page: perPage.value,
      },
    })
    contacts.value = res.data ?? []
    total.value = res.total ?? contacts.value.length
  } finally {
    loading.value = false
  }
}

const saveContact = async () => {
  await $api('/contacts', { method: 'POST', body: form.value })
  dialog.value = false
  form.value = { name: '', email: '', phone: '', company: '' }
  await fetchContacts()
}

const deleteContact = async id => {
  await $api(`/contacts/${id}`, { method: 'DELETE' })
  await fetchContacts()
}

watch([page, perPage], fetchContacts)

onMounted(fetchContacts)
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4">
      <h5 class="text-h5">
        مخاطبین
      </h5>
      <VBtn
        prepend-icon="tabler-plus"
        @click="dialog = true"
      >
        مخاطب جدید
      </VBtn>
    </VCardText>
    <VDataTableServer
      :headers="headers"
      :items="contacts"
      :items-length="total"
      :loading="loading"
      v-model:page="page"
      v-model:items-per-page="perPage"
      class="cursor-pointer"
      @click:row="(_, { item }) => router.push({ name: 'apps-crm-contacts-id', params: { id: item.id } })"
    >
      <template #item.products="{ item }">
        <CrmProductChips :products="item.products ?? []" />
      </template>
      <template #item.actions="{ item }">
        <IconBtn @click.stop="router.push({ name: 'apps-crm-contacts-id', params: { id: item.id } })">
          <VIcon icon="tabler-eye" />
        </IconBtn>
        <IconBtn @click.stop="deleteContact(item.id)">
          <VIcon icon="tabler-trash" />
        </IconBtn>
      </template>
    </VDataTableServer>
  </VCard>

  <VDialog
    v-model="dialog"
    max-width="500"
  >
    <VCard title="مخاطب جدید">
      <VCardText>
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="form.name"
              label="نام"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="form.email"
              label="ایمیل"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="form.phone"
              label="تلفن"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="form.company"
              label="شرکت"
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
          @click="saveContact"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
