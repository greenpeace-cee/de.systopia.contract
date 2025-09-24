export function initForm() {
  const attachmentBlock = cj(`div.form-field#contract_file div.attachment`);

  attachmentBlock.find(`a#delete-attachment`).click(({ target }) => {
    const fileId = target.getAttribute("data-file-id");
    const fileName = target.getAttribute("data-file-name");

    CRM.confirm({
        title: "Delete contract file",
        message: `
          <p>Are you sure you want to delete the attached contract file <i>${fileName}</i> ?</p>
          <p>The file will be deleted immediately, this action cannot be undone!</p>
        `,
        options: { yes: "Yes", no: "No" },
    }).on("crmConfirm:yes", () => {
      CRM.api4("File", "delete", { where: [["id", "=", fileId]] }).then(
        () => attachmentBlock.remove(),
        (error) => console.error(error),
      );
    });
  });
}
