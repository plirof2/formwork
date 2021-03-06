export default function RangeInput(input) {
    input.addEventListener('change', updateValueLabel);
    input.addEventListener('input', updateValueLabel);

    function updateValueLabel() {
        $('.range-input-value', this.parentNode).innerHTML = this.value;
    }
}
