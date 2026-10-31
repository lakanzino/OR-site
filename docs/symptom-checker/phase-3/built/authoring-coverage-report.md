# گزارش پوشش بستهٔ دانش

- بسته: `symptom-checker.abdomen.v1` · نسخه `0.2.0-draft` · schema `2.0.0`
- 28 نشانه · 18 پرسش · 6 بیماری · 7 override

## نشانه‌هایی که هیچ پرسشی نمی‌تواند گزارش کند

- `abd.bladder_pressure`
- `abd.abdominal_cramp`

## نشانه‌هایی که در هیچ پروفایلی نیستند

- `abd.blood_stool`

## بیماری‌های بدون جداکننده

- هیچ

## جفت‌های هم‌پوشان

| بیماری | بیماری | نشانه‌های مشترک | جداکننده‌ها |
| --- | --- | --- | --- |
| app | kidney_stone | abd.nausea, abd.vomiting | abd.appetite_loss, abd.bloating, abd.cannot_urinate, abd.cloudy_urine, abd.colicky_pain, abd.constipation, abd.diarrhea, abd.dysuria, abd.fever, abd.flank_pain, abd.frequency, abd.hematuria, abd.no_gas_pass, abd.pain_abdomen, abd.pain_migrates_rlq, abd.pain_periumbilical_start, abd.pain_rlq, abd.pain_worse_movement, abd.radiate_groin, abd.urgency |
| app | kidney_infection | abd.fever, abd.nausea, abd.vomiting | abd.appetite_loss, abd.bloating, abd.chills, abd.constipation, abd.diarrhea, abd.dysuria, abd.flank_pain, abd.frequency, abd.no_gas_pass, abd.pain_abdomen, abd.pain_migrates_rlq, abd.pain_periumbilical_start, abd.pain_rlq, abd.pain_worse_movement |
| app | gastroenteritis | abd.diarrhea, abd.fever, abd.nausea, abd.vomiting | abd.appetite_loss, abd.bloating, abd.constipation, abd.dehydration, abd.no_gas_pass, abd.pain_abdomen, abd.pain_migrates_rlq, abd.pain_periumbilical_start, abd.pain_rlq, abd.pain_worse_movement |
| app | chole | abd.nausea, abd.pain_abdomen | abd.appetite_loss, abd.bloating, abd.constipation, abd.diarrhea, abd.fever, abd.no_gas_pass, abd.pain_after_meal, abd.pain_migrates_rlq, abd.pain_periumbilical_start, abd.pain_rlq, abd.pain_worse_movement, abd.vomiting |
| kidney_stone | kidney_infection | abd.dysuria, abd.flank_pain, abd.frequency, abd.nausea, abd.vomiting | abd.cannot_urinate, abd.chills, abd.cloudy_urine, abd.colicky_pain, abd.fever, abd.hematuria, abd.radiate_groin, abd.urgency |
| kidney_stone | urinary_tract | abd.cloudy_urine, abd.dysuria, abd.frequency, abd.hematuria, abd.urgency | abd.cannot_urinate, abd.colicky_pain, abd.flank_pain, abd.nausea, abd.radiate_groin, abd.vomiting |
| kidney_stone | gastroenteritis | abd.nausea, abd.vomiting | abd.cannot_urinate, abd.cloudy_urine, abd.colicky_pain, abd.dehydration, abd.diarrhea, abd.dysuria, abd.fever, abd.flank_pain, abd.frequency, abd.hematuria, abd.radiate_groin, abd.urgency |
| kidney_stone | chole | abd.nausea | abd.cannot_urinate, abd.cloudy_urine, abd.colicky_pain, abd.dysuria, abd.flank_pain, abd.frequency, abd.hematuria, abd.pain_abdomen, abd.pain_after_meal, abd.radiate_groin, abd.urgency, abd.vomiting |
| kidney_infection | urinary_tract | abd.dysuria, abd.frequency | abd.chills, abd.cloudy_urine, abd.fever, abd.flank_pain, abd.hematuria, abd.nausea, abd.urgency, abd.vomiting |
| kidney_infection | gastroenteritis | abd.fever, abd.nausea, abd.vomiting | abd.chills, abd.dehydration, abd.diarrhea, abd.dysuria, abd.flank_pain, abd.frequency |
| kidney_infection | chole | abd.nausea | abd.chills, abd.dysuria, abd.fever, abd.flank_pain, abd.frequency, abd.pain_abdomen, abd.pain_after_meal, abd.vomiting |
| gastroenteritis | chole | abd.nausea | abd.dehydration, abd.diarrhea, abd.fever, abd.pain_abdomen, abd.pain_after_meal, abd.vomiting |

بازبینی: این گزارش فقط ساختار و پوشش را می‌سنجد؛ هیچ ادعای بالینی نمی‌کند.
