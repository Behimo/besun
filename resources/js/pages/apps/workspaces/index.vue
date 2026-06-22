<script setup>
const workspaces = ref([])
const loading = ref(true)
const dialog = ref(false)
const name = ref('')

const fetchWorkspaces = async () => {
  loading.value = true
  try {
    const res = await $api('/workspaces')
    workspaces.value = res.workspaces ?? []
  } finally {
    loading.value = false
  }
}

const createWorkspace = async () => {
  await $api('/workspaces', { method: 'POST', body: { name: name.value } })
  dialog.value = false
  name.value = ''
  await fetchWorkspaces()
}

const switchWorkspace = async workspace => {
  await $api(`/workspaces/${workspace.id}/switch`, { method: 'POST' })
  const userData = useCookie('userData')
  userData.value = {
    ...userData.value,
    workspace: { id: workspace.id, name: workspace.name },
  }
  window.location.reload()
}

onMounted(fetchWorkspaces)
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4">
      <h5 class="text-h5">
        مجموعه‌ها (Workspace)
      </h5>
      <VBtn
        prepend-icon="tabler-plus"
        @click="dialog = true"
      >
        مجموعه جدید
      </VBtn>
    </VCardText>
    <VList>
      <VListItem
        v-for="ws in workspaces"
        :key="ws.id"
        :title="ws.name"
        :subtitle="ws.is_default ? 'پیش‌فرض' : ''"
      >
        <template #append>
          <VBtn
            size="small"
            variant="tonal"
            @click="switchWorkspace(ws)"
          >
            انتخاب
          </VBtn>
        </template>
      </VListItem>
    </VList>
  </VCard>

  <VDialog
    v-model="dialog"
    max-width="400"
  >
    <VCard title="مجموعه جدید">
      <VCardText>
        <AppTextField
          v-model="name"
          label="نام مجموعه"
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="dialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          @click="createWorkspace"
        >
          ایجاد
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
