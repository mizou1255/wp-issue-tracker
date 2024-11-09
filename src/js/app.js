import { createApp, h } from "vue";
import Dashboard from "@/components/Dashboard.vue";

const app_dash = createApp({
  render: () => h(Dashboard),
});
app_dash.mount("#wpissuetracker-admin-app");
