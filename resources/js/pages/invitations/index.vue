<script setup>
const invitations = ref([])
const loading = ref(true)
const message = ref('')

const fetchInvitations = async () => {
  loading.value = true
  try {
    const res = await $api('/invitations')
    invitations.value = res.invitations ?? []
  } finally {
    loading.value = false
  }
}

const accept = async id => {
  try {
    await $api(`/invitations/${id}/accept`, { method: 'POST' })
    message.value = 'دعوت‌نامه پذیرفته شد.'
    await fetchInvitations()
  } catch (e) {
    message.value = e?.data?.message || 'خطا'
  }
}

const reject = async id => {
  try {
    await $api(`/invitations/${id}/reject`, { method: 'POST' })
    await fetchInvitations()
  } catch (e) {
    message.value = e?.data?.message || 'خطا'
  }
}

onMounted(fetchInvitations)
</script>

<template>
  <VCard>
    <VCardText>
      <h5 class="text-h5 mb-4">
        دعوتنامه‌های من
      </h5>

      <VAlert
        v-if="message"
        type="success"
        variant="tonal"
        class="mb-4"
      >
        {{ message }}
      </VAlert>

      <VList v-if="invitations.length">
        <VListItem
          v-for="inv in invitations"
          :key="inv.id"
          :title="inv.tenant?.name"
          :subtitle="`نقش: ${inv.role === 'admin' ? 'مدیر' : 'کارمند'} — از طرف ${inv.inviter?.name || '—'}`"
        >
          <template #append>
            <div class="d-flex gap-2">
              <VBtn
                size="small"
                color="primary"
                @click="accept(inv.id)"
              >
                پذیرش
              </VBtn>
              <VBtn
                size="small"
                variant="tonal"
                @click="reject(inv.id)"
              >
                رد
              </VBtn>
            </div>
          </template>
        </VListItem>
      </VList>

      <VAlert
        v-else-if="!loading"
        type="info"
        variant="tonal"
      >
        دعوت‌نامه در انتظاری ندارید.
      </VAlert>
    </VCardText>
  </VCard>
</template>
