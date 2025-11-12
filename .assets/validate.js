document.getElementById('orderForm').addEventListener('submit', function (e) {
    const nameInput = document.querySelector('input[name="name"]');
    const seatInput = document.querySelector('input[name="seat"]');
    const telNumber = document.querySelector('input[name="tel_number"]');
    const emailInput = document.querySelector('input[name="email"]');

    document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));

    let valid = true;

    const nameValue = nameInput.value.trim();
    if (nameValue.length < 3 || /^\d+$/.test(nameValue)) {
        nameInput.classList.add('error');
        nameInput.previousElementSibling.classList.add('error');
        valid = false;
    }

    const seatPattern = /^[A-Za-z]\d{4}$/;
    if (!seatPattern.test(seatInput.value.trim())) {
        seatInput.classList.add('error');
        seatInput.previousElementSibling.classList.add('error');
        valid = false;
    }

    if (!telPrefix.value) {
        telPrefix.classList.add('error');
        telPrefix.parentElement.previousElementSibling.classList.add('error');
        valid = false;
    }

    const digits = telNumber.value.replace(/\D+/g, '');
    if (digits.length < 6 || digits.length > 12) {
        telNumber.classList.add('error');
        telPrefix.classList.add('error');
        valid = false;
    }

    const emailPattern = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/;
    if (!emailPattern.test(emailInput.value.trim())) {
        emailInput.classList.add('error');
        emailInput.previousElementSibling.classList.add('error');
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
    }
});
