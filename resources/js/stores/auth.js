import { defineStore } from "pinia";
import { ref, computed } from "vue";
import axios from "axios";

export const useAuthStore = defineStore("auth", () => {
    const user = ref(null);
    const tenant = ref(null);
    const loading = ref(false);

    const isAuthenticated = computed(() => user.value !== null);
    const isSuperAdmin = computed(() => user.value?.role === "super_admin");
    const isAdmin = computed(() => user.value?.role === "admin");
    const isManager = computed(() =>
        ["admin", "manager"].includes(user.value?.role)
    );
    const isEmployee = computed(() => user.value?.role === "employee");

    const hasManagerAccess = computed(() =>
        ["super_admin", "admin", "manager"].includes(user.value?.role)
    );

    const hasAdminAccess = computed(() =>
        ["super_admin", "admin"].includes(user.value?.role)
    );

    async function fetchUser() {
        try {
            loading.value = true;
            const response = await axios.get("/api/user");
            user.value = response.data;
            tenant.value = response.data.tenant;
            return response.data;
        } catch (error) {
            user.value = null;
            tenant.value = null;
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function login(credentials) {
        const response = await axios.post("/login", credentials);
        await fetchUser();
        return response.data;
    }

    async function logout() {
        await axios.post("/logout");
        user.value = null;
        tenant.value = null;
    }

    function setUser(userData) {
        user.value = userData;
        tenant.value = userData.tenant;
    }

    return {
        user,
        tenant,
        loading,
        isAuthenticated,
        isSuperAdmin,
        isAdmin,
        isManager,
        isEmployee,
        hasManagerAccess,
        hasAdminAccess,
        fetchUser,
        login,
        logout,
        setUser,
    };
});
