<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    title: 'Validação Concluída!',
    text: 'Seu WhatsApp foi validado com sucesso!',
    icon: 'success',
    confirmButtonText: 'Acessar Área do Cliente',
    confirmButtonColor: '#25D366'
}).then((result) => {
    if (result.isConfirmed) {
        window.location.href = 'clientarea.php';
    }
});
</script>